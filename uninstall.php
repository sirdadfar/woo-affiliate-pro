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

global $wpdb;

$settings     = get_option( 'wap_settings', array() );
$shouldDelete = is_array( $settings ) && ! empty( $settings['delete_data_on_uninstall'] );

wp_clear_scheduled_hook( 'wap_daily_maintenance' );

/*
 * قفل‌ها و transientها حتی وقتی دادهٔ مالی نگه داشته می‌شود باید پاک شوند؛
 * در غیر این صورت بعد از نصب دوباره، یک قفل کهنه تا TTL خودش باقی می‌ماند.
 */
$patterns = array(
	$wpdb->esc_like( '_transient_wap_click_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_wap_click_' ) . '%',
	$wpdb->esc_like( '_transient_wap_rl_' ) . '%',
	$wpdb->esc_like( '_transient_timeout_wap_rl_' ) . '%',
	$wpdb->esc_like( 'wap_lock_' ) . '%',
);

$where = array();

foreach ( $patterns as $pattern ) {
	$where[] = $wpdb->prepare( 'option_name LIKE %s', $pattern );
}

if ( ! empty( $where ) ) {
	$wpdb->query( // phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared -- شرط‌ها همین‌جا با prepare ساخته شده‌اند.
		'DELETE FROM ' . $wpdb->options . ' WHERE ' . implode( ' OR ', $where )
	);
}

if ( ! $shouldDelete ) {
	return;
}

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
