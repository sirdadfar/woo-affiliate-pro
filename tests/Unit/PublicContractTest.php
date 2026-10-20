<?php
/**
 * قراردادهای عمومی: نام شورت‌کدها، مسیرهای REST، پارامتر لینک معرف،
 * کلید آپشن و روش‌های تسویه. تغییر هر کدام یک شکست سازگاری است.
 *
 * @package WooAffiliatePro
 */

declare( strict_types=1 );

namespace WooAffiliatePro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WooAffiliatePro\Domain\Payout\PayoutRequest;
use WooAffiliatePro\Integration\Admin\AdminMenu;
use WooAffiliatePro\Integration\OrderListener;
use WooAffiliatePro\Integration\ReferralTracker;
use WooAffiliatePro\Integration\RestController;
use WooAffiliatePro\Support\Settings;

final class PublicContractTest extends TestCase {

	private const ROOT = __DIR__ . '/../..';

	public function test_shortcodes_keep_their_public_names(): void {
		$source = (string) file_get_contents( self::ROOT . '/src/Integration/Shortcodes.php' );

		self::assertStringContainsString( "add_shortcode( 'wap_dashboard'", $source );
		self::assertStringContainsString( "add_shortcode( 'wap_join'", $source );
	}

	public function test_referral_query_parameter_is_ref(): void {
		self::assertSame( 'ref', ReferralTracker::QUERY_PARAM );
	}

	public function test_rest_namespace_and_routes_are_unchanged(): void {
		self::assertSame( 'wap/v1', RestController::NAMESPACE_V1 );

		$source = (string) file_get_contents( self::ROOT . '/src/Integration/RestController.php' );

		foreach ( array( "'/me'", "'/payouts'", "'/affiliates'" ) as $route ) {
			self::assertStringContainsString( $route, $source, 'مسیر REST حذف شده است: ' . $route );
		}
	}

	public function test_admin_menu_slug_and_setting_option_are_unchanged(): void {
		self::assertSame( 'woo-affiliate-pro', AdminMenu::SLUG );
		self::assertSame( 'wap_settings', Settings::OPTION );
	}

	public function test_payout_methods_and_order_meta_keys_are_unchanged(): void {
		self::assertSame( array( 'card', 'bank', 'wallet' ), PayoutRequest::METHODS );

		self::assertSame( '_wap_affiliate_id', OrderListener::META_AFFILIATE );
		self::assertSame( '_wap_ref_code', OrderListener::META_CODE );
		self::assertSame( '_wap_commission_total', OrderListener::META_TOTAL );
		self::assertSame( '_wap_self_referral', OrderListener::META_SELF );
	}
}
