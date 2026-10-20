<?php
/**
 * قراردادهای امنیتی که نباید بی‌سروصدا حذف شوند.
 *
 * این تست‌ها رفتار وردپرس را شبیه‌سازی نمی‌کنند؛ فقط مطمئن می‌شوند
 * گارد دسترسی مستقیم، nonce، capability و permission_callback در
 * فایل‌های حساس باقی مانده‌اند. حذف هر کدام باید تست را بشکند.
 *
 * @package WooAffiliatePro
 */

declare( strict_types=1 );

namespace WooAffiliatePro\Tests\Unit;

use PHPUnit\Framework\TestCase;

final class SecurityContractTest extends TestCase {

	private const ROOT = __DIR__ . '/../..';

	/**
	 * @return string[]
	 */
	private function phpFiles( string $directory ): array {
		$files    = array();
		$iterator = new \RecursiveIteratorIterator(
			new \RecursiveDirectoryIterator( $directory, \FilesystemIterator::SKIP_DOTS )
		);

		foreach ( $iterator as $file ) {
			if ( $file instanceof \SplFileInfo && 'php' === strtolower( $file->getExtension() ) ) {
				$files[] = $file->getPathname();
			}
		}

		sort( $files );

		return $files;
	}

	public function test_every_source_file_blocks_direct_access(): void {
		$files = $this->phpFiles( self::ROOT . '/src' );

		self::assertGreaterThanOrEqual( 30, count( $files ), 'فایل‌های src کمتر از انتظار است؛ تست بی‌معنا می‌شود.' );

		foreach ( $files as $file ) {
			$source = (string) file_get_contents( $file );

			self::assertMatchesRegularExpression(
				"/defined\(\s*'ABSPATH'\s*\)\s*\|\|\s*exit;/",
				$source,
				'گارد دسترسی مستقیم ندارد: ' . $file
			);
		}
	}

	public function test_templates_and_bootstrap_are_guarded(): void {
		$templates = $this->phpFiles( self::ROOT . '/templates' );

		self::assertNotEmpty( $templates );

		foreach ( $templates as $file ) {
			self::assertMatchesRegularExpression(
				"/defined\(\s*'ABSPATH'\s*\)\s*\|\|\s*exit;/",
				(string) file_get_contents( $file ),
				'قالب بدون گارد دسترسی مستقیم: ' . $file
			);
		}

		self::assertStringContainsString(
			"defined( 'ABSPATH' ) || exit;",
			(string) file_get_contents( self::ROOT . '/woo-affiliate-pro.php' )
		);

		self::assertStringContainsString(
			"defined( 'WP_UNINSTALL_PLUGIN' ) || exit;",
			(string) file_get_contents( self::ROOT . '/uninstall.php' )
		);
	}

	public function test_dangerous_php_constructs_are_absent_from_source(): void {
		foreach ( $this->phpFiles( self::ROOT . '/src' ) as $file ) {
			$source = (string) file_get_contents( $file );

			self::assertDoesNotMatchRegularExpression( '/\bextract\s*\(/', $source, 'extract() در ' . $file );
			self::assertDoesNotMatchRegularExpression( '/\beval\s*\(/', $source, 'eval() در ' . $file );
			self::assertDoesNotMatchRegularExpression( '/\b(base64_decode|gzinflate|str_rot13)\s*\(/', $source, 'رمزگشایی پنهان در ' . $file );
			self::assertDoesNotMatchRegularExpression( '/\bunserialize\s*\(/', $source, 'unserialize() در ' . $file );
			self::assertStringNotContainsString( '$_REQUEST', $source, '$_REQUEST در ' . $file );
		}
	}

	public function test_admin_actions_keep_capability_and_nonce_checks(): void {
		$source = (string) file_get_contents( self::ROOT . '/src/Integration/Admin/AdminMenu.php' );

		self::assertStringContainsString( 'current_user_can( $capability )', $source );
		self::assertStringContainsString( 'check_admin_referer( $nonceAction )', $source );

		foreach ( array( 'wap_affiliate_status', 'wap_commission_status', 'wap_payout_status', 'wap_save_settings' ) as $hook ) {
			self::assertStringContainsString(
				"add_action( 'admin_post_" . $hook . "'",
				$source,
				'اکشن ادمین ثبت نشده است: ' . $hook
			);
		}

		// فرم تنظیمات و هندلر باید یک اکشن nonce مشترک داشته باشند.
		self::assertStringContainsString( "wp_nonce_field( 'wap_save_settings' )", $source );
		self::assertStringContainsString( "guard( 'manage_options', 'wap_save_settings' )", $source );
	}

	public function test_rest_routes_declare_real_permission_callbacks(): void {
		$source = (string) file_get_contents( self::ROOT . '/src/Integration/RestController.php' );

		self::assertStringNotContainsString( '__return_true', $source );
		self::assertSame( 3, substr_count( $source, "'permission_callback'" ) );
		self::assertStringContainsString( "current_user_can( 'manage_woocommerce' )", $source );
		self::assertStringContainsString( 'is_user_logged_in()', $source );
	}

	public function test_admin_request_helpers_reject_array_input(): void {
		$source = (string) file_get_contents( self::ROOT . '/src/Integration/Admin/AdminMenu.php' );

		self::assertStringContainsString( '! is_scalar( $_POST[ $key ] )', $source );
		self::assertStringContainsString( '! is_scalar( $_GET[ $key ] )', $source );
	}
}
