<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WooAffiliatePro\Domain\Commission\Status;
use WooAffiliatePro\Domain\Money;
use WooAffiliatePro\Domain\Payout\BalanceCalculator;
use WooAffiliatePro\Domain\Payout\PayoutRequest;
use WooAffiliatePro\Domain\Payout\PayoutStatus;

final class BalanceTest extends TestCase {

	/** @var BalanceCalculator */
	private $calculator;

	protected function setUp(): void {
		$this->calculator = new BalanceCalculator();
	}

	public function test_available_is_earned_minus_paid_minus_pending(): void {
		$balance = $this->calculator->calculate(
			array(
				array( 'amount' => 100_000, 'status' => Status::APPROVED ),
				array( 'amount' => 50_000, 'status' => Status::PAID ),
				array( 'amount' => 30_000, 'status' => Status::PENDING ), // هنوز تأیید نشده
			),
			array(
				array( 'amount' => 40_000, 'status' => PayoutStatus::PAID ),
				array( 'amount' => 10_000, 'status' => PayoutStatus::REQUESTED ),
			)
		);

		self::assertSame( 150_000, $balance->earned()->amount() );
		self::assertSame( 40_000, $balance->paid()->amount() );
		self::assertSame( 10_000, $balance->pendingWithdrawals()->amount() );
		self::assertSame( 100_000, $balance->available()->amount() );
	}

	public function test_pending_commission_is_not_withdrawable(): void {
		$balance = $this->calculator->calculate(
			array( array( 'amount' => 500_000, 'status' => Status::PENDING ) ),
			array()
		);

		self::assertSame( 0, $balance->available()->amount() );
	}

	public function test_reversed_commission_is_tracked_separately(): void {
		$balance = $this->calculator->calculate(
			array(
				array( 'amount' => 100_000, 'status' => Status::PAID ),
				array( 'amount' => 100_000, 'status' => Status::REVERSED ),
			),
			array()
		);

		self::assertSame( 100_000, $balance->earned()->amount() );
		self::assertSame( 100_000, $balance->reversed()->amount() );
	}

	public function test_cannot_withdraw_more_than_available(): void {
		$balance = $this->calculator->calculate(
			array( array( 'amount' => 100_000, 'status' => Status::APPROVED ) ),
			array()
		);

		$request = new PayoutRequest( Money::of( 150_000 ), 'card' );
		$errors  = $request->validate( $balance, Money::of( 50_000 ) );

		self::assertArrayHasKey( PayoutRequest::ERROR_INSUFFICIENT, $errors );
		self::assertFalse( $request->isValid( $balance, Money::of( 50_000 ) ) );
	}

	public function test_cannot_withdraw_below_minimum(): void {
		$balance = $this->calculator->calculate(
			array( array( 'amount' => 100_000, 'status' => Status::APPROVED ) ),
			array()
		);

		$request = new PayoutRequest( Money::of( 20_000 ), 'card' );
		$errors  = $request->validate( $balance, Money::of( 50_000 ) );

		self::assertArrayHasKey( PayoutRequest::ERROR_BELOW_MINIMUM, $errors );
	}

	public function test_zero_amount_is_rejected(): void {
		$balance = $this->calculator->calculate( array(), array() );
		$request = new PayoutRequest( Money::zero(), 'card' );
		$errors  = $request->validate( $balance, Money::zero() );

		self::assertArrayHasKey( PayoutRequest::ERROR_NEGATIVE, $errors );
	}

	public function test_invalid_method_is_rejected(): void {
		$balance = $this->calculator->calculate(
			array( array( 'amount' => 100_000, 'status' => Status::APPROVED ) ),
			array()
		);

		$request = new PayoutRequest( Money::of( 100_000 ), 'crypto' );
		$errors  = $request->validate( $balance, Money::of( 10_000 ) );

		self::assertArrayHasKey( PayoutRequest::ERROR_INVALID_METHOD, $errors );
	}

	public function test_valid_request_passes(): void {
		$balance = $this->calculator->calculate(
			array( array( 'amount' => 100_000, 'status' => Status::APPROVED ) ),
			array()
		);

		$request = new PayoutRequest( Money::of( 60_000 ), 'card' );

		self::assertTrue( $request->isValid( $balance, Money::of( 50_000 ) ) );
		self::assertSame( array(), $request->validate( $balance, Money::of( 50_000 ) ) );
	}

	public function test_second_request_is_blocked_while_first_is_pending(): void {
		$balance = $this->calculator->calculate(
			array( array( 'amount' => 100_000, 'status' => Status::APPROVED ) ),
			array( array( 'amount' => 100_000, 'status' => PayoutStatus::REQUESTED ) )
		);

		self::assertSame( 0, $balance->available()->amount() );

		$second = new PayoutRequest( Money::of( 10_000 ), 'card' );
		self::assertFalse( $second->isValid( $balance, Money::zero() ) );
	}

	public function test_to_array_shape(): void {
		$balance = $this->calculator->calculate(
			array( array( 'amount' => 100_000, 'status' => Status::APPROVED ) ),
			array()
		);
		$array = $balance->toArray();

		self::assertSame( 100_000, $array['available'] );
		self::assertSame( 'IRR', $array['currency'] );
	}
}
