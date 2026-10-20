<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration;

defined( 'ABSPATH' ) || exit;

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

		// مقدار فیلتر می‌تواند از یک افزونهٔ دیگر بیاید؛ حداقل/حداکثر جلوی حذف ناخواسته را می‌گیرد.
		$days = (int) apply_filters( 'wap_click_retention_days', 180 );
		$days = max( 1, min( 3650, $days ) );

		$wpdb->query(
			$wpdb->prepare(
				'DELETE FROM ' . $wpdb->prefix . 'wap_clicks WHERE created_at < %s',
				gmdate( 'Y-m-d H:i:s', time() - ( $days * 86400 ) )
			)
		);
	}

	private static function approveMissed(): void {
		$approveOn = Settings::string( 'approve_on' );

		// اگر تأیید روی «در حال انجام» تنظیم شده، سفارش تکمیل‌شده هم حتماً باید تأیید شود.
		$allowed = 'processing' === $approveOn
			? array( 'processing', 'completed' )
			: array( 'completed' );

		$repository = new CommissionRepository();

		// صفحه‌بندی ثابت روی صفحهٔ ۱ است چون ردیف‌های تأییدشده از نتیجهٔ pending حذف می‌شوند.
		// سقف ۲۰ دور جلوی حلقهٔ بی‌پایان در صورت خطای همیشگی دیتابیس را می‌گیرد.
		for ( $batch = 0; $batch < 20; $batch++ ) {
			$rows = $repository->paginate( 1, 100, Status::PENDING );

			if ( empty( $rows ) ) {
				return;
			}

			$changed = 0;

			foreach ( $rows as $row ) {
				$order = wc_get_order( (int) $row['order_id'] );

				if ( ! $order || ! $order->is_paid() ) {
					continue;
				}

				if ( ! in_array( (string) $order->get_status(), $allowed, true ) ) {
					continue;
				}

				if ( Status::canTransition( Status::PENDING, Status::APPROVED )
					&& $repository->updateStatus( (int) $row['id'], Status::APPROVED, 'تأیید خودکار توسط کار روزانه' ) ) {
					++$changed;
				}
			}

			if ( 0 === $changed ) {
				return;
			}
		}
	}
}
