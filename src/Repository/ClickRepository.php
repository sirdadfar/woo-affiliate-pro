<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Repository;

defined( 'ABSPATH' ) || exit;

/**
 * ثبت کلیک‌های لینک معرف.
 *
 * IP به‌صورت هش ذخیره می‌شود، نه خام. برای تشخیص تقلب کافی است
 * (همان IP → همان هش) ولی اگر دیتابیس لو برود، آدرس IP بازدیدکننده‌ها
 * در دسترس نمی‌باشد.
 */
final class ClickRepository {

	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wap_clicks';
	}

	public function log(
		int $affiliateId,
		string $ipAddress,
		string $userAgent,
		string $referrer,
		string $landingUrl,
		string $salt = ''
	): int {
		global $wpdb;

		$inserted = $wpdb->insert(
			$this->table(),
			array(
				'affiliate_id' => $affiliateId,
				'ip_hash'      => hash_hmac( 'sha256', $ipAddress, $salt ),
				'user_agent'   => mb_substr( $userAgent, 0, 255 ),
				'referrer'     => mb_substr( $referrer, 0, 255 ),
				'landing_url'  => mb_substr( $landingUrl, 0, 255 ),
				'created_at'   => current_time( 'mysql', true ),
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		if ( false === $inserted ) {
			return 0;
		}

		return (int) $wpdb->insert_id;
	}

	public function countAll(): int {
		global $wpdb;

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table() );
	}

	public function countForAffiliate( int $affiliateId ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE affiliate_id = %d', $affiliateId )
		);
	}

	public function countSince( int $affiliateId, int $timestamp ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE affiliate_id = %d AND created_at >= %s',
				$affiliateId,
				gmdate( 'Y-m-d H:i:s', $timestamp )
			)
		);
	}
}
