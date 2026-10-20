<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Support;

defined( 'ABSPATH' ) || exit;

/**
 * محدودکنندهٔ نرخ ساده بر پایهٔ transient.
 *
 * برای مسیرهای عمومی/نیمه‌عمومی (ثبت‌نام و درخواست تسویه) استفاده
 * می‌شود تا یک حساب نتواند در چند ثانیه ده‌ها رکورد بسازد. این
 * محدودکننده جایگزین قفل اتمیک نیست؛ لایهٔ دوم است.
 */
final class RateLimiter {

	private const PREFIX = 'wap_rl_';

	/**
	 * @return bool true اگر در بازهٔ مجاز باشد.
	 */
	public static function allow( string $key, int $limit, int $windowSeconds ): bool {
		$transient = self::PREFIX . md5( $key );
		$window    = max( 1, $windowSeconds );
		$now       = time();
		$state     = get_transient( $transient );

		if ( ! is_array( $state ) || ! isset( $state['count'], $state['reset'] ) || (int) $state['reset'] <= $now ) {
			$state = array(
				'count' => 0,
				'reset' => $now + $window,
			);
		}

		++$state['count'];

		set_transient( $transient, $state, $window );

		return (int) $state['count'] <= max( 1, $limit );
	}

	public static function clear( string $key ): void {
		delete_transient( self::PREFIX . md5( $key ) );
	}
}
