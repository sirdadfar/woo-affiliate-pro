<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Referral;

defined( 'ABSPATH' ) || exit;

/**
 * قواعد انتساب کلیک به نماینده.
 *
 * تصمیم‌های مهمی که این کلاس شفاف می‌کند:
 *   - «آخرین کلیک» برنده است، نه اولین کلیک. خریدار معمولاً چند بار
 *     لینک‌های مختلف را باز می‌کند و آخرین لینک، نیت نهایی اوست.
 *   - کد نامعتبر هرگز جای کد معتبر قبلی را نمی‌گیرد (وگرنه هر کسی با
 *     فرستادن ?ref=xxx می‌تواند انتساب را خراب کند).
 *   - خریدار نمی‌تواند از لینک خودش خرید کند.
 */
final class CookiePolicy {

	public const DEFAULT_DAYS = 30;

	private const SECONDS_PER_DAY = 86400;

	/** @var int */
	private $ttlDays;

	/** @var string */
	private $cookieName;

	public function __construct( int $ttlDays = self::DEFAULT_DAYS, string $cookieName = 'wap_ref' ) {
		if ( $ttlDays < 1 || $ttlDays > 365 ) {
			throw new \InvalidArgumentException( 'مدت اعتبار کوکی باید بین ۱ تا ۳۶۵ روز باشد.' );
		}

		$this->ttlDays    = $ttlDays;
		$this->cookieName = $cookieName;
	}

	public function ttlDays(): int {
		return $this->ttlDays;
	}

	public function ttlSeconds(): int {
		return $this->ttlDays * self::SECONDS_PER_DAY;
	}

	public function cookieName(): string {
		return $this->cookieName;
	}

	/**
	 * استخراج کد معرف از آرایهٔ کوئری استرینگ.
	 *
	 * @param array<string, mixed> $query
	 */
	public function extractFromQuery( array $query, string $param = 'ref' ): ?string {
		if ( ! isset( $query[ $param ] ) || ! is_string( $query[ $param ] ) ) {
			return null;
		}

		$raw = $query[ $param ];

		// اعتبارسنجی روی مقدار خام انجام می‌شود، نه نرمال‌شده؛ وگرنه
		// ورودی‌هایی مثل «../../etc/passwd» پس از حذف جداکننده‌ها معتبر به نظر می‌رسند.
		if ( ! CodeGenerator::isValid( $raw ) ) {
			return null;
		}

		return CodeGenerator::normalize( $raw );
	}

	/**
	 * آیا کد جدید باید جای کد ذخیره‌شده را بگیرد؟
	 *
	 * @param string|null $existingCode کد فعلی کوکی
	 * @param string      $newCode      کد تازه از URL
	 * @param int         $ageSeconds   عمر کوکی فعلی
	 */
	public function shouldReplace( ?string $existingCode, string $newCode, int $ageSeconds = 0 ): bool {
		if ( ! CodeGenerator::isValid( $newCode ) ) {
			return false;
		}
		if ( null === $existingCode || '' === $existingCode ) {
			return true;
		}
		if ( 0 === strcasecmp( $existingCode, $newCode ) ) {
			return false; // همان کد؛ نیازی به تمدید نوشتن نیست
		}

		return $ageSeconds < $this->ttlSeconds();
	}

	/**
	 * آیا این خرید از خود نماینده است؟
	 *
	 * @param array{user_id?: int, email?: string} $affiliate
	 * @param array{user_id?: int, email?: string} $customer
	 */
	public function isSelfReferral( array $affiliate, array $customer ): bool {
		$affiliateUserId = isset( $affiliate['user_id'] ) ? (int) $affiliate['user_id'] : 0;
		$customerUserId  = isset( $customer['user_id'] ) ? (int) $customer['user_id'] : 0;

		if ( $affiliateUserId > 0 && $affiliateUserId === $customerUserId ) {
			return true;
		}

		$affiliateEmail = isset( $affiliate['email'] ) ? strtolower( trim( (string) $affiliate['email'] ) ) : '';
		$customerEmail  = isset( $customer['email'] ) ? strtolower( trim( (string) $customer['email'] ) ) : '';

		return '' !== $affiliateEmail && $affiliateEmail === $customerEmail;
	}

	/**
	 * آیا کوکی منقضی شده است؟
	 */
	public function isExpired( int $createdAt, int $now ): bool {
		return ( $now - $createdAt ) > $this->ttlSeconds();
	}
}
