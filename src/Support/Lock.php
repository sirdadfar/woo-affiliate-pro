<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Support;

defined( 'ABSPATH' ) || exit;

/**
 * قفل کوتاه‌مدت برای جلوگیری از ثبت دوبارهٔ یک عملیات مالی.
 *
 * چرا option و نه transient؟ `add_option` روی کلید یکتای جدول options
 * به‌صورت اتمیک عمل می‌کند؛ دو درخواست هم‌زمان نمی‌توانند هر دو
 * «قفل را بگیرند». transient روی دیتابیس چنین تضمینی ندارد.
 *
 * قفل با TTL خودش منقضی می‌شود تا اگر پراسس وسط کار از بین رفت،
 * کاربر برای همیشه قفل نماند.
 */
final class Lock {

	private const PREFIX = 'wap_lock_';

	public static function acquire( string $key, int $ttlSeconds = 30 ): bool {
		$option = self::PREFIX . md5( $key );
		$ttl    = max( 5, $ttlSeconds );

		if ( add_option( $option, time(), '', false ) ) {
			return true;
		}

		$created = (int) get_option( $option );

		if ( $created > 0 && $created < ( time() - $ttl ) ) {
			delete_option( $option );

			return (bool) add_option( $option, time(), '', false );
		}

		return false;
	}

	public static function release( string $key ): void {
		delete_option( self::PREFIX . md5( $key ) );
	}
}
