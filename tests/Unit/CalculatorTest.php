<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WooAffiliatePro\Domain\Commission\Calculator;
use WooAffiliatePro\Domain\Commission\CommissionPolicy;
use WooAffiliatePro\Domain\Order\OrderLine;
use WooAffiliatePro\Domain\Order\OrderSnapshot;
use WooAffiliatePro\Domain\Money;

final class CalculatorTest extends TestCase {

	private function line( int $productId, int $total, array $categories = array(), int $tax = 0 ): OrderLine {
		return new OrderLine(
			$productId,
			'محصول ' . $productId,
			$categories,
			Money::of( $total ),
			Money::of( $tax )
		);
	}

	private function order( array $lines, int $shipping = 0 ): OrderSnapshot {
		return new OrderSnapshot(
			1001,
			$lines,
			Money::of( $shipping ),
			Money::zero()
		);
	}

	public function test_default_percent_is_applied_per_line(): void {
		$order  = $this->order( array( $this->line( 1, 1_000_000 ), $this->line( 2, 500_000 ) ) );
		$result = ( new Calculator() )->calculate( $order, CommissionPolicy::percent( 1000 ) ); // ۱۰٪

		self::assertSame( 150_000, $result->total()->amount() );
		self::assertCount( 2, $result->lines() );
	}

	public function test_category_rate_overrides_default(): void {
		$policy = CommissionPolicy::percent( 500 )->withCategoryRates(
			array( 12 => array( 'type' => CommissionPolicy::TYPE_PERCENT, 'value' => 2000 ) )
		);

		$order = $this->order(
			array(
				$this->line( 1, 1_000_000, array( 12 ) ), // ۲۰٪
				$this->line( 2, 1_000_000, array( 5 ) ),  // ۵٪ پیش‌فرض
			)
		);

		$result = ( new Calculator() )->calculate( $order, $policy );

		self::assertSame( 250_000, $result->total()->amount() );
	}

	public function test_lowest_category_id_wins_when_product_has_multiple_rates(): void {
		$policy = CommissionPolicy::percent( 100 )->withCategoryRates(
			array(
				30 => array( 'type' => CommissionPolicy::TYPE_PERCENT, 'value' => 3000 ),
				10 => array( 'type' => CommissionPolicy::TYPE_PERCENT, 'value' => 1000 ),
			)
		);

		$order  = $this->order( array( $this->line( 1, 1_000_000, array( 30, 10 ) ) ) );
		$result = ( new Calculator() )->calculate( $order, $policy );

		self::assertSame( 100_000, $result->total()->amount() );
	}

	public function test_excluded_product_earns_nothing(): void {
		$policy = new CommissionPolicy(
			CommissionPolicy::TYPE_PERCENT,
			1000,
			null,
			false,
			false,
			array(),
			array( 99 )
		);

		$order  = $this->order( array( $this->line( 99, 1_000_000 ), $this->line( 1, 1_000_000 ) ) );
		$result = ( new Calculator() )->calculate( $order, $policy );

		self::assertSame( 100_000, $result->total()->amount() );
		self::assertNotEmpty( $result->notes() );
	}

	public function test_excluded_category_earns_nothing(): void {
		$policy = new CommissionPolicy(
			CommissionPolicy::TYPE_PERCENT,
			1000,
			null,
			false,
			false,
			array(),
			array(),
			null,
			array( 7 )
		);

		$order  = $this->order( array( $this->line( 1, 1_000_000, array( 7 ) ) ) );
		$result = ( new Calculator() )->calculate( $order, $policy );

		self::assertSame( 0, $result->total()->amount() );
		self::assertTrue( $result->isEmpty() );
	}

	public function test_tax_is_ignored_by_default(): void {
		$order  = $this->order( array( $this->line( 1, 1_000_000, array(), 90_000 ) ) );
		$result = ( new Calculator() )->calculate( $order, CommissionPolicy::percent( 1000 ) );

		self::assertSame( 100_000, $result->total()->amount() );
	}

	public function test_tax_is_included_when_policy_says_so(): void {
		$policy = new CommissionPolicy( CommissionPolicy::TYPE_PERCENT, 1000, null, false, true );
		$order  = $this->order( array( $this->line( 1, 1_000_000, array(), 90_000 ) ) );

		$result = ( new Calculator() )->calculate( $order, $policy );

		self::assertSame( 109_000, $result->total()->amount() );
	}

	public function test_shipping_is_included_only_when_enabled(): void {
		$order = $this->order( array( $this->line( 1, 1_000_000 ) ), 50_000 );

		$without = ( new Calculator() )->calculate( $order, CommissionPolicy::percent( 1000 ) );
		self::assertSame( 100_000, $without->total()->amount() );

		$with = ( new Calculator() )->calculate(
			$order,
			new CommissionPolicy( CommissionPolicy::TYPE_PERCENT, 1000, null, true )
		);
		self::assertSame( 105_000, $with->total()->amount() );
	}

	public function test_fixed_commission_is_applied_once_per_order(): void {
		$policy = CommissionPolicy::fixed( Money::of( 50_000 ) );
		$order  = $this->order( array( $this->line( 1, 1_000_000 ), $this->line( 2, 2_000_000 ) ) );

		$result = ( new Calculator() )->calculate( $order, $policy );

		self::assertSame( 50_000, $result->total()->amount() );
		self::assertCount( 1, $result->lines() );
	}

	public function test_cap_produces_adjustment_line_so_lines_sum_to_total(): void {
		$policy = CommissionPolicy::percent( 1000 )->withMaxCommission( Money::of( 80_000 ) );
		$order  = $this->order( array( $this->line( 1, 1_000_000 ), $this->line( 2, 1_000_000 ) ) );

		$result = ( new Calculator() )->calculate( $order, $policy );

		self::assertSame( 80_000, $result->total()->amount() );
		self::assertTrue( $result->isCapped() );

		$sum = 0;
		foreach ( $result->lines() as $line ) {
			$sum += $line->amount()->amount();
		}
		self::assertSame( 80_000, $sum, 'جمع سطرها باید برابر مبلغ نهایی باشد' );
	}

	public function test_empty_order_yields_zero(): void {
		$result = ( new Calculator() )->calculate( OrderSnapshot::empty( 5 ), CommissionPolicy::percent( 1000 ) );

		self::assertTrue( $result->isEmpty() );
		self::assertSame( 0, $result->total()->amount() );
	}

	public function test_zero_percent_policy_returns_zero_without_lines(): void {
		$order  = $this->order( array( $this->line( 1, 1_000_000 ) ) );
		$result = ( new Calculator() )->calculate( $order, CommissionPolicy::percent( 0 ) );

		self::assertSame( 0, $result->total()->amount() );
	}

	public function test_breakdown_to_array_is_serialisable(): void {
		$order  = $this->order( array( $this->line( 1, 1_000_000 ) ) );
		$result = ( new Calculator() )->calculate( $order, CommissionPolicy::percent( 1000 ) );
		$array  = $result->toArray();

		self::assertSame( 100_000, $array['total'] );
		self::assertSame( 1000, $array['lines'][0]['basis_points'] );
		self::assertJson( (string) json_encode( $array ) );
	}

	public function test_invalid_policy_is_rejected(): void {
		$this->expectException( \InvalidArgumentException::class );

		new CommissionPolicy( 'percent', 12000 ); // بیش از ۱۰۰٪
	}

	public function test_fixed_policy_requires_amount(): void {
		$this->expectException( \InvalidArgumentException::class );

		new CommissionPolicy( CommissionPolicy::TYPE_FIXED, 0, null );
	}
}
