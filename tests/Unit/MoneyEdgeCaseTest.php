<?php
/**
 * لبه‌های پولی: گرد کردن، اعشار، ارز و سرریز.
 *
 * @package WooAffiliatePro
 */

declare( strict_types=1 );

namespace WooAffiliatePro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WooAffiliatePro\Domain\Money;

final class MoneyEdgeCaseTest extends TestCase {

	public function test_from_float_rounds_half_away_from_zero(): void {
		self::assertSame( 1, Money::fromFloat( 0.5 )->amount() );
		self::assertSame( 3, Money::fromFloat( 2.5 )->amount() );
		self::assertSame( -1, Money::fromFloat( -0.5 )->amount() );
		self::assertSame( -3, Money::fromFloat( -2.5 )->amount() );
	}

	public function test_from_string_handles_arabic_indic_digits_and_separators(): void {
		self::assertSame( 1250000, Money::fromString( '١,٢٥٠,٠٠٠' )->amount() );
		self::assertSame( 1250000, Money::fromString( "1\u{200c}250\u{200c}000" )->amount() );
		self::assertSame( 12500, Money::fromString( ' 12 500 ' )->amount() );
	}

	public function test_from_string_rejects_non_numeric_input(): void {
		$this->expectException( \InvalidArgumentException::class );

		Money::fromString( '12; DROP TABLE wp_options' );
	}

	public function test_format_respects_decimal_places(): void {
		self::assertSame( '123.45', Money::of( 12345, 'USD', 2 )->format() );
		self::assertSame( '0.05', Money::of( 5, 'USD', 2 )->format() );
		self::assertSame( '1,234', Money::of( 1234, 'IRR', 0 )->format() );
	}

	public function test_currency_is_normalized_to_uppercase(): void {
		$money = Money::of( 100, 'usd', 2 );

		self::assertSame( 'USD', $money->currency() );
		self::assertSame( 2, $money->decimals() );
	}

	public function test_decimal_places_are_bounded(): void {
		$this->expectException( \InvalidArgumentException::class );

		Money::of( 1, 'IRR', 5 );
	}

	public function test_negative_decimals_are_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );

		Money::of( 1, 'IRR', -1 );
	}

	public function test_percent_rounds_negative_amounts_half_away_from_zero(): void {
		self::assertSame( -1, Money::of( -50 )->percent( 100 )->amount() );
		self::assertSame( 0, Money::of( -49 )->percent( 100 )->amount() );
	}

	public function test_percent_refuses_amounts_that_could_overflow(): void {
		$this->expectException( \OverflowException::class );

		Money::of( intdiv( PHP_INT_MAX, 10000 ) + 1 )->percent( 1000 );
	}

	public function test_string_cast_includes_the_currency(): void {
		self::assertSame( '1,250 USD', (string) Money::of( 1250, 'USD' ) );
	}
}
