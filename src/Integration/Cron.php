<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration;

use WooAffiliatePro\Domain\Commission\Status;
use WooAffiliatePro\Repository\CommissionRepository;
use WooAffiliatePro\Support\Settings;

/**
 * کارهای روزانه: پاک‌سازی کلیک‌های قدیمی و جبران پورسانت‌های جاافتاده.
 *
 * چرا جبران لازم است؟ هوک `woocommerce_order_status_changed` اگر به هر دلیلی
 * (خطای افزونهٔ دیگر، ری‌استارت سرور، ایمپورت سفارش) اجرا نشود، پورسانت
 * در وضعیت pending می‌ماند و نماینده هرگز پولش را نمی‌گیرد. این کار روزانه
 * سفارش‌های تکمیل‌شده‌ای را که پورسانتشان هنوز تأیید نشده پیدا می‌کند.
 */
final class Cron {

	public static function run(): void {
		self::pruneClicks();
		self::approveMissed();
	}

	private static function pruneClicks(): void {
		global $wpdb;

		$days = (int) apply_filters( 'wap_click_retention_days', 180 );

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . $wpdb->prefix . 'wap_clicks WHERE created_at < %s',
				gmdate( 'Y-m-d H:i:s', time() - ( $days * 86400 ) )
			)
		);
	}

	private static function approveMissed(): void {
		if ( 'completed' !== Settings::string( 'approve_on' ) ) {
			return;
		}

		$repository = new CommissionRepository();

		$rows = $repository->paginate( 1, 50, Status::PENDING );

		foreach ( $rows as $row ) {
			$order = wc_get_order( (int) $row['order_id'] );

			if ( ! $order || ! $order->is_paid() ) {
				continue;
			}

			if ( ! in_array( $order->get_status(), array( 'completed' ), true ) ) {
				continue;
			}

			if ( Status::canTransition( Status::PENDING, Status::APPROVED ) ) {
				$repository->updateStatus( (int) $row['id'], Status::APPROVED, 'تأیید خودکار توسط کار روزانه' );
			}
		}
	}
}
