<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration;

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Domain\Referral\CookiePolicy;
use WooAffiliatePro\Repository\AffiliateRepository;
use WooAffiliatePro\Repository\ClickRepository;
use WooAffiliatePro\Support\Settings;

/**
 * گرفتن ?ref=CODE از URL، ست کردن کوکی و ثبت کلیک.
 *
 * روی هوک `init` اجرا می‌شود چون کوکی باید پیش از ارسال هدرها ست شود؛
 * اگر روی `template_redirect` بگذاریم، روی برخی قالب‌ها هدرها
 * زودتر فرستاده شده‌اند و کوکی ست نمی‌شود.
 */
final class ReferralTracker {

	/** نام پارامتر در URL */
	public const QUERY_PARAM = 'ref';

	/** @var AffiliateRepository */
	private $affiliates;

	/** @var ClickRepository */
	private $clicks;

	/** @var CookiePolicy */
	private $policy;

	public function __construct( ?AffiliateRepository $affiliates = null, ?ClickRepository $clicks = null ) {
		$this->affiliates = $affiliates ?: new AffiliateRepository();
		$this->clicks     = $clicks ?: new ClickRepository();
		$this->policy     = new CookiePolicy( Settings::cookieDays() );
	}

	public function register(): void {
		add_action( 'init', array( $this, 'maybeCapture' ), 1 );
	}

	public function maybeCapture(): void {
		if ( is_admin() || wp_doing_ajax() || wp_doing_cron() ) {
			return;
		}

		if ( ! isset( $_GET[ self::QUERY_PARAM ] ) ) {
			return;
		}

		// phpcs:ignore WordPress.Security.NonceVerification.Recommended -- لینک عمومی معرف، nonce ندارد.
		$raw = wp_unslash( $_GET[ self::QUERY_PARAM ] );

		if ( ! is_string( $raw ) ) {
			return;
		}

		$code = $this->policy->extractFromQuery( array( self::QUERY_PARAM => $raw ) );

		if ( null === $code ) {
			return;
		}

		$affiliate = $this->affiliates->findByCode( $code );

		if ( null === $affiliate || AffiliateRepository::STATUS_ACTIVE !== $affiliate['status'] ) {
			return;
		}

		$cookieName = $this->policy->cookieName();

		$existing = isset( $_COOKIE[ $cookieName ] ) && is_string( $_COOKIE[ $cookieName ] )
			? sanitize_text_field( wp_unslash( $_COOKIE[ $cookieName ] ) )
			: null;
		$age      = isset( $_COOKIE[ $cookieName . '_ts' ] ) && is_scalar( $_COOKIE[ $cookieName . '_ts' ] )
			? ( time() - (int) $_COOKIE[ $cookieName . '_ts' ] )
			: 0;

		if ( $this->policy->shouldReplace( $existing, $code, $age ) ) {
			$this->setCookie( $cookieName, $code );
			$this->setCookie( $cookieName . '_ts', (string) time() );
		}

		$userAgent = isset( $_SERVER['HTTP_USER_AGENT'] ) && is_string( $_SERVER['HTTP_USER_AGENT'] )
			? sanitize_text_field( wp_unslash( $_SERVER['HTTP_USER_AGENT'] ) )
			: '';
		$ip        = $this->clientIp();

		/*
		 * هر بازدیدکننده در هر ۶۰ ثانیه فقط یک کلیک ثبت می‌کند تا رفرش صفحه
		 * آمار را باد نکند؛ اما کلیک هم‌زمان دو کاربر مختلف نادیده گرفته
		 * نمی‌شود. کلید = شناسهٔ نماینده + هش کوتاه IP و User-Agent.
		 * (کلید سراسری قبلی، کلیک همهٔ کاربران را در یک بازهٔ ۳۰ ثانیه‌ای
		 *  در یک ردیف جمع می‌کرد و آمار را کم‌نمایش می‌داد.)
		 */
		$visitor  = substr( md5( $ip . '|' . $userAgent ), 0, 16 );
		$clickKey = 'wap_click_' . (int) $affiliate['id'] . '_' . $visitor;

		if ( false === get_transient( $clickKey ) ) {
			$this->clicks->log(
				(int) $affiliate['id'],
				$ip,
				$userAgent,
				isset( $_SERVER['HTTP_REFERER'] ) && is_string( $_SERVER['HTTP_REFERER'] ) ? esc_url_raw( wp_unslash( $_SERVER['HTTP_REFERER'] ) ) : '',
				home_url( add_query_arg( array() ) ),
				wp_salt( 'auth' )
			);
			set_transient( $clickKey, 1, 60 );
		}
	}

	/**
	 * کد معرف ذخیره‌شده در کوکی، در صورت وجود.
	 */
	public function currentCode(): ?string {
		$cookieName = $this->policy->cookieName();

		if ( ! isset( $_COOKIE[ $cookieName ] ) ) {
			return null;
		}

		if ( ! is_string( $_COOKIE[ $cookieName ] ) ) {
			return null;
		}

		$code = sanitize_text_field( wp_unslash( $_COOKIE[ $cookieName ] ) );

		return \WooAffiliatePro\Domain\Referral\CodeGenerator::isValid( $code )
			? \WooAffiliatePro\Domain\Referral\CodeGenerator::normalize( $code )
			: null;
	}

	private function setCookie( string $name, string $value ): void {
		if ( headers_sent() ) {
			return;
		}

		setcookie(
			$name,
			$value,
			array(
				'expires'  => time() + $this->policy->ttlSeconds(),
				'path'     => COOKIEPATH ?: '/',
				'domain'   => COOKIE_DOMAIN,
				'secure'   => is_ssl(),
				'httponly' => true,
				'samesite' => 'Lax',
			)
		);
	}

	private function clientIp(): string {
		// پشت کلودفلر/پروکسی، REMOTE_ADDR خود پروکسی است؛ این هدرها فقط برای آمار است.
		foreach ( array( 'HTTP_CF_CONNECTING_IP', 'HTTP_X_FORWARDED_FOR', 'REMOTE_ADDR' ) as $key ) {
			if ( empty( $_SERVER[ $key ] ) ) {
				continue;
			}

			if ( ! is_scalar( $_SERVER[ $key ] ) ) {
				continue;
			}

			$value = sanitize_text_field( wp_unslash( $_SERVER[ $key ] ) );
			$first = trim( explode( ',', $value )[0] );

			if ( filter_var( $first, FILTER_VALIDATE_IP ) ) {
				return $first;
			}
		}

		return '0.0.0.0';
	}
}
