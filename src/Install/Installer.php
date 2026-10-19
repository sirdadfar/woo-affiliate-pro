<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Install;

/**
 * نصب، ارتقا و حذف جداول.
 *
 * از dbDelta استفاده می‌شود چون تنها روشی است که وردپرس برای تغییر
 * بی‌خطر ساختار جدول بدون از دست رفتن داده پیشنهاد می‌کند.
 */
final class Installer {

	public const CRON_HOOK = 'wap_daily_maintenance';

	public static function activate(): void {
		self::createTables();
		self::seedOptions();
		self::scheduleCron();

		update_option( 'wap_db_version', WAP_DB_VERSION, false );
	}

	public static function deactivate(): void {
		wp_clear_scheduled_hook( self::CRON_HOOK );
	}

	/**
	 * روی هر بار بالا آمدن اجرا می‌شود؛ اگر نسخهٔ دیتابیس قدیمی باشد
	 * جداول را به‌روز می‌کند (dbDelta خودش تشخیص می‌دهد چه چیزی تغییر کرده).
	 */
	public static function maybeUpgrade(): void {
		$installed = get_option( 'wap_db_version' );

		if ( WAP_DB_VERSION === $installed ) {
			return;
		}

		self::createTables();
		self::seedOptions();
		update_option( 'wap_db_version', WAP_DB_VERSION, false );
	}

	private static function createTables(): void {
		global $wpdb;

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset = $wpdb->get_charset_collate();
		$prefix  = $wpdb->prefix;

		$affiliates = "CREATE TABLE {$prefix}wap_affiliates (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
			code VARCHAR(32) NOT NULL,
			name VARCHAR(190) NOT NULL DEFAULT '',
			email VARCHAR(190) NOT NULL DEFAULT '',
			mobile VARCHAR(20) NOT NULL DEFAULT '',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			rate_basis_points INT NOT NULL DEFAULT 1000,
			payout_method VARCHAR(20) NOT NULL DEFAULT 'card',
			payout_details TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY code (code),
			KEY user_id (user_id),
			KEY email (email),
			KEY status (status)
		) {$charset};";

		$clicks = "CREATE TABLE {$prefix}wap_clicks (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			affiliate_id BIGINT UNSIGNED NOT NULL,
			ip_hash CHAR(64) NOT NULL DEFAULT '',
			user_agent VARCHAR(255) NOT NULL DEFAULT '',
			referrer VARCHAR(255) NOT NULL DEFAULT '',
			landing_url VARCHAR(255) NOT NULL DEFAULT '',
			created_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			KEY affiliate_created (affiliate_id, created_at)
		) {$charset};";

		$commissions = "CREATE TABLE {$prefix}wap_commissions (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			affiliate_id BIGINT UNSIGNED NOT NULL,
			order_id BIGINT UNSIGNED NOT NULL,
			amount BIGINT NOT NULL DEFAULT 0,
			base_amount BIGINT NOT NULL DEFAULT 0,
			rate_basis_points INT NOT NULL DEFAULT 0,
			currency VARCHAR(3) NOT NULL DEFAULT 'IRR',
			status VARCHAR(20) NOT NULL DEFAULT 'pending',
			note TEXT NULL,
			created_at DATETIME NOT NULL,
			updated_at DATETIME NOT NULL,
			PRIMARY KEY  (id),
			UNIQUE KEY order_affiliate (order_id, affiliate_id),
			KEY affiliate_status (affiliate_id, status),
			KEY status (status)
		) {$charset};";

		$payouts = "CREATE TABLE {$prefix}wap_payouts (
			id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
			affiliate_id BIGINT UNSIGNED NOT NULL,
			amount BIGINT NOT NULL DEFAULT 0,
			method VARCHAR(20) NOT NULL DEFAULT 'card',
			status VARCHAR(20) NOT NULL DEFAULT 'requested',
			reference VARCHAR(190) NOT NULL DEFAULT '',
			note TEXT NULL,
			requested_at DATETIME NOT NULL,
			paid_at DATETIME NULL,
			PRIMARY KEY  (id),
			KEY affiliate_status (affiliate_id, status),
			KEY status (status)
		) {$charset};";

		dbDelta( $affiliates );
		dbDelta( $clicks );
		dbDelta( $commissions );
		dbDelta( $payouts );
	}

	private static function seedOptions(): void {
		$existing = get_option( 'wap_settings' );

		if ( false === $existing || ! is_array( $existing ) ) {
			add_option( 'wap_settings', \WooAffiliatePro\Support\Settings::defaults(), '', false );
		}
	}

	private static function scheduleCron(): void {
		if ( ! wp_next_scheduled( self::CRON_HOOK ) ) {
			wp_schedule_event( time() + HOUR_IN_SECONDS, 'daily', self::CRON_HOOK );
		}
	}
}
