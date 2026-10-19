<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WooAffiliatePro\Domain\Referral\CookiePolicy;

final class CookiePolicyTest extends TestCase {

	public function test_extracts_valid_ref_from_query(): void {
		$policy = new CookiePolicy();

		self::assertSame( 'AB12CD', $policy->extractFromQuery( array( 'ref' => 'ab12cd' ) ) );
		self::assertSame( 'AB12CD', $policy->extractFromQuery( array( 'ref' => ' AB-12-CD ' ) ) );
	}

	public function test_ignores_missing_or_invalid_ref(): void {
		$policy = new CookiePolicy();

		self::assertNull( $policy->extractFromQuery( array() ) );
		self::assertNull( $policy->extractFromQuery( array( 'ref' => array( 'x' ) ) ) );
		self::assertNull( $policy->extractFromQuery( array( 'ref' => '<script>alert(1)</script>' ) ) );
		self::assertNull( $policy->extractFromQuery( array( 'utm' => 'AB12CD' ) ) );
	}

	public function test_supports_custom_parameter_name(): void {
		$policy = new CookiePolicy( 30, 'wap_ref' );

		self::assertSame( 'AB12CD', $policy->extractFromQuery( array( 'aff' => 'AB12CD' ), 'aff' ) );
	}

	public function test_first_click_sets_cookie(): void {
		$policy = new CookiePolicy();

		self::assertTrue( $policy->shouldReplace( null, 'AB12CD' ) );
	}

	public function test_same_code_does_not_rewrite_cookie(): void {
		$policy = new CookiePolicy();

		self::assertFalse( $policy->shouldReplace( 'AB12CD', 'ab12cd' ) );
	}

	public function test_last_click_wins_within_ttl(): void {
		$policy = new CookiePolicy( 30 );

		self::assertTrue( $policy->shouldReplace( 'OLD111', 'NEW222', 60 ) );
	}

	public function test_expired_cookie_is_replaced(): void {
		$policy = new CookiePolicy( 30 );

		// ۱۰۰ روز بعد از کلیک قبلی
		self::assertFalse( $policy->shouldReplace( 'OLD111', 'NEW222', 100 * 86400 ) );
	}

	public function test_invalid_new_code_never_replaces(): void {
		$policy = new CookiePolicy();

		self::assertFalse( $policy->shouldReplace( 'OLD111', 'x', 0 ) );
	}

	public function test_self_referral_by_user_id(): void {
		$policy = new CookiePolicy();

		self::assertTrue( $policy->isSelfReferral( array( 'user_id' => 7 ), array( 'user_id' => 7 ) ) );
		self::assertFalse( $policy->isSelfReferral( array( 'user_id' => 7 ), array( 'user_id' => 8 ) ) );
	}

	public function test_self_referral_by_email_is_case_insensitive(): void {
		$policy = new CookiePolicy();

		self::assertTrue(
			$policy->isSelfReferral(
				array( 'email' => 'Ali@Example.com' ),
				array( 'email' => ' ali@example.COM ' )
			)
		);
	}

	public function test_guest_without_email_is_not_self_referral(): void {
		$policy = new CookiePolicy();

		self::assertFalse( $policy->isSelfReferral( array(), array() ) );
	}

	public function test_expiry_check(): void {
		$policy = new CookiePolicy( 1 );
		$now    = 1_700_000_000;

		self::assertFalse( $policy->isExpired( $now - 3600, $now ) );
		self::assertTrue( $policy->isExpired( $now - 90000, $now ) );
	}

	public function test_ttl_is_validated(): void {
		$this->expectException( \InvalidArgumentException::class );

		new CookiePolicy( 0 );
	}

	public function test_ttl_seconds(): void {
		$policy = new CookiePolicy( 30 );

		self::assertSame( 30 * 86400, $policy->ttlSeconds() );
	}
}
