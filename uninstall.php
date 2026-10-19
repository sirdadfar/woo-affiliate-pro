<?php
/**
 * حذف افزونه.
 *
 * تصمیم مهم: داده‌های مالی (پورسانت و تسویه) به‌صورت پیش‌فرض حذف نمی‌شوند.
 * اگر مدیر افزونه را موقتاً غیرفعال کند و داده‌ها پاک شوند، سابقهٔ پرداخت
 * از بین می‌رود و حساب نمایندگان قابل بازسازی نیست. پاک‌سازی فقط وقتی
 * انجام می‌شود که مدیر در تنظیمات صریحاً آن را خواسته باشد.
 *
 * @package WooAffiliatePro
 */

defined( 'WP_UNINSTALL_PLUGIN' ) || exit;

$settings = get_option( 'wap_settings', array() );

$shouldDelete = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );

if ( ! $shouldDelete ) {
	return;
}

global $wpdb;

$tables = array(
	$wpdb->prefix . 'wap_commissions',
	$wpdb->prefix . 'wap_payouts',
	$wpdb->prefix . 'wap_clicks',
	$wpdb->prefix . 'wap_affiliates',
);

foreach ( $tables as $table ) {
	// نام جدول از پیشوند خود وردپرس ساخته می‌شود، ورودی کاربر نیست.
	$wpdb->query( "DROP TABLE IF EXISTS {$table}" ); // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
}

delete_option( 'wap_settings' );
delete_option( 'wap_db_version' );

wp_clear_scheduled_hook( 'wap_daily_maintenance' );
