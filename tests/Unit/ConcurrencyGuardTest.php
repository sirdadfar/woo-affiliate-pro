<?php
/**
 * رفتار محدودکنندهٔ نرخ و قفل هم‌زمانی.
 *
 * این دو کلاس به transient/option وردپرس وابسته‌اند؛ به‌جای نصب وردپرس،
 * یک انبار درون‌حافظه‌ای با همان قرارداد حداقلی شبیه‌سازی می‌شود.
 * هدف تست، منطق شمارش و انقضاست، نه خود وردپرس.
 *
 * @package WooAffiliatePro
 */

declare( strict_types=1 );

namespace {

	if ( ! function_exists( 'wap_test_transient_store' ) ) {
		/**
		 * @return array<string, mixed>
		 */
		function &wap_test_transient_store(): array {
			static $store = array();

			return $store;
		}
	}

	if ( ! function_exists( 'wap_test_option_store' ) ) {
		/**
		 * @return array<string, mixed>
		 */
		function &wap_test_option_store(): array {
			static $store = array();

			return $store;
		}
	}

	if ( ! function_exists( 'wap_test_reset_stores' ) ) {
		function wap_test_reset_stores(): void {
			$transients = &wap_test_transient_store();
			$options    = &wap_test_option_store();

			$transients = array();
			$options    = array();
		}
	}

	if ( ! function_exists( 'get_transient' ) ) {
		/**
		 * @param string $key
		 *
		 * @return mixed
		 */
		function get_transient( $key ) {
			$store = &wap_test_transient_store();

			return array_key_exists( $key, $store ) ? $store[ $key ] : false;
		}
	}

	if ( ! function_exists( 'set_transient' ) ) {
		/**
		 * @param string $key
		 * @param mixed  $value
		 * @param int    $expiration
		 */
		function set_transient( $key, $value, $expiration = 0 ): bool {
			$store         = &wap_test_transient_store();
			$store[ $key ] = $value;

			return true;
		}
	}

	if ( ! function_exists( 'delete_transient' ) ) {
		/**
		 * @param string $key
		 */
		function delete_transient( $key ): bool {
			$store = &wap_test_transient_store();

			unset( $store[ $key ] );

			return true;
		}
	}

	if ( ! function_exists( 'add_option' ) ) {
		/**
		 * @param string $option
		 * @param mixed  $value
		 * @param string $deprecated
		 * @param mixed  $autoload
		 */
		function add_option( $option, $value = '', $deprecated = '', $autoload = 'yes' ): bool {
			$store = &wap_test_option_store();

			if ( array_key_exists( $option, $store ) ) {
				return false;
			}

			$store[ $option ] = $value;

			return true;
		}
	}

	if ( ! function_exists( 'get_option' ) ) {
		/**
		 * @param string $option
		 * @param mixed  $default
		 *
		 * @return mixed
		 */
		function get_option( $option, $default = false ) {
			$store = &wap_test_option_store();

			return array_key_exists( $option, $store ) ? $store[ $option ] : $default;
		}
	}

	if ( ! function_exists( 'delete_option' ) ) {
		/**
		 * @param string $option
		 */
		function delete_option( $option ): bool {
			$store = &wap_test_option_store();

			unset( $store[ $option ] );

			return true;
		}
	}
}

namespace WooAffiliatePro\Tests\Unit {

	use PHPUnit\Framework\TestCase;
	use WooAffiliatePro\Support\Lock;
	use WooAffiliatePro\Support\RateLimiter;

	final class ConcurrencyGuardTest extends TestCase {

		protected function setUp(): void {
			\wap_test_reset_stores();
		}

		public function test_rate_limiter_allows_up_to_the_limit_then_blocks(): void {
			self::assertTrue( RateLimiter::allow( 'payout:7', 2, 60 ) );
			self::assertTrue( RateLimiter::allow( 'payout:7', 2, 60 ) );
			self::assertFalse( RateLimiter::allow( 'payout:7', 2, 60 ) );
		}

		public function test_rate_limiter_keeps_keys_independent(): void {
			self::assertTrue( RateLimiter::allow( 'payout:7', 1, 60 ) );
			self::assertFalse( RateLimiter::allow( 'payout:7', 1, 60 ) );
			self::assertTrue( RateLimiter::allow( 'payout:8', 1, 60 ) );
		}

		public function test_rate_limiter_starts_a_new_window_after_expiry(): void {
			$key = 'wap_rl_' . md5( 'payout:9' );

			\set_transient(
				$key,
				array(
					'count' => 99,
					'reset' => time() - 1,
				),
				60
			);

			self::assertTrue( RateLimiter::allow( 'payout:9', 2, 60 ) );
		}

		public function test_clear_resets_the_counter(): void {
			self::assertTrue( RateLimiter::allow( 'register:3:127.0.0.1', 1, 60 ) );
			self::assertFalse( RateLimiter::allow( 'register:3:127.0.0.1', 1, 60 ) );

			RateLimiter::clear( 'register:3:127.0.0.1' );

			self::assertTrue( RateLimiter::allow( 'register:3:127.0.0.1', 1, 60 ) );
		}

		public function test_lock_is_exclusive_until_released(): void {
			self::assertTrue( Lock::acquire( 'payout:7', 30 ) );
			self::assertFalse( Lock::acquire( 'payout:7', 30 ) );

			Lock::release( 'payout:7' );

			self::assertTrue( Lock::acquire( 'payout:7', 30 ) );
		}

		public function test_lock_keys_do_not_collide(): void {
			self::assertTrue( Lock::acquire( 'payout:7', 30 ) );
			self::assertTrue( Lock::acquire( 'payout:8', 30 ) );
		}

		public function test_expired_lock_can_be_reclaimed(): void {
			self::assertTrue( Lock::acquire( 'register:12', 15 ) );

			$options = &\wap_test_option_store();
			$options[ 'wap_lock_' . md5( 'register:12' ) ] = time() - 60;

			self::assertTrue( Lock::acquire( 'register:12', 15 ), 'قفل کهنه باید قابل بازپس‌گیری باشد.' );
		}
	}
}
