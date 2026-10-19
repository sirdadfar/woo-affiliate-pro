<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WooAffiliatePro\Domain\Money;

final class MoneyTest extends TestCase {

	public function test_it_never_loses_precision_on_repeated_addition(): void {
		$total = Money::of( 0, 'IRR', 1 );

		// ۱۰۰ بار جمع کردن ۰.۱ تومان؛ با float نتیجه ۹.۹۹۹... می‌شد.
		for ( $i = 0; $i < 100; $i++ ) {
			$total = $total->add( Money::fromFloat( 0.1, 'IRR', 1 ) );
		}

		self::assertSame( 100, $total->amount() );
		self::assertSame( 10.0, $total->toFloat() );
	}

	public function test_percent_is_computed_from_integers(): void {
		$amount = Money::of( 1_000_000 ); // ۱۰۰ هزار تومان

		self::assertSame( 100_000, $amount->percent( 1000 )->amount() ); // ۱۰٪
		self::assertSame( 25_000, $amount->percent( 250 )->amount() );   // ۲.۵٪
		self::assertSame( 0, $amount->percent( 0 )->amount() );
		self::assertSame( 1_000_000, $amount->percent( 10000 )->amount() ); // ۱۰۰٪
	}

	public function test_percent_rounds_half_up(): void {
		// ۱٪ از ۵۰ = ۰.۵ → ۱ (نیم‌به‌بالا)
		self::assertSame( 1, Money::of( 50 )->percent( 100 )->amount() );

		// ۱٪ از ۴۹ = ۰.۴۹ → ۰
		self::assertSame( 0, Money::of( 49 )->percent( 100 )->amount() );
	}

	public function test_rejects_percent_below_zero(): void {
		$this->expectException( \InvalidArgumentException::class );

		Money::of( 1000 )->percent( -10 );
	}

	public function test_it_refuses_mixed_currencies(): void {
		$this->expectException( \InvalidArgumentException::class );

		Money::of( 1000, 'IRR' )->add( Money::of( 1000, 'USD' ) );
	}

	public function test_subtract_can_go_negative_for_reversals(): void {
		$result = Money::of( 500_000 )->subtract( Money::of( 800_000 ) );

		self::assertSame( -300_000, $result->amount() );
		self::assertTrue( $result->isNegative() );
	}

	public function test_compare_and_equality(): void {
		$a = Money::of( 1000 );
		$b = Money::of( 2000 );

		self::assertTrue( $a->lessThan( $b ) );
		self::assertTrue( $b->greaterThan( $a ) );
		self::assertTrue( $a->equals( Money::of( 1000 ) ) );
		self::assertSame( $a, $a->min( $b ) );
	}

	public function test_from_string_accepts_persian_formatted_numbers(): void {
		self::assertSame( 1250000, Money::fromString( '1,250,000' )->amount() );
		self::assertSame( 1250000, Money::fromString( '۱۲۵۰۰۰۰' )->amount() );
	}

	public function test_format_uses_thousands_separator(): void {
		self::assertSame( '1,250,000', Money::of( 1_250_000 )->format() );
	}

	public function test_it_supports_currencies_with_decimals(): void {
		$usd = Money::fromFloat( 19.99, 'USD', 2 );

		self::assertSame( 1999, $usd->amount() );
		self::assertSame( '19.99', $usd->format() );
		self::assertSame( 200, $usd->percent( 1000 )->amount() ); // ۱۰٪ = ۲.۰۰
	}
}
