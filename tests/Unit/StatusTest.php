<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WooAffiliatePro\Domain\Commission\Status;
use WooAffiliatePro\Domain\Payout\PayoutStatus;

final class StatusTest extends TestCase {

	public function test_happy_path_transitions(): void {
		self::assertTrue( Status::canTransition( Status::PENDING, Status::APPROVED ) );
		self::assertTrue( Status::canTransition( Status::APPROVED, Status::PAID ) );
		self::assertTrue( Status::canTransition( Status::PAID, Status::REVERSED ) );
	}

	public function test_cannot_skip_approval(): void {
		self::assertFalse( Status::canTransition( Status::PENDING, Status::PAID ) );
	}

	public function test_cannot_reverse_a_pending_commission_directly(): void {
		self::assertFalse( Status::canTransition( Status::PENDING, Status::REVERSED ) );
	}

	public function test_reversed_is_final(): void {
		self::assertTrue( Status::isFinal( Status::REVERSED ) );
		self::assertSame( array(), Status::nextStates( Status::REVERSED ) );
	}

	public function test_rejected_can_be_reapproved_after_review(): void {
		self::assertTrue( Status::canTransition( Status::REJECTED, Status::APPROVED ) );
	}

	public function test_same_status_is_a_noop_not_an_error(): void {
		self::assertTrue( Status::canTransition( Status::PAID, Status::PAID ) );
	}

	public function test_unknown_status_throws(): void {
		$this->expectException( \InvalidArgumentException::class );

		Status::canTransition( 'teleported', Status::PAID );
	}

	public function test_earning_statuses_include_paid(): void {
		self::assertSame( array( Status::APPROVED, Status::PAID ), Status::earning() );
	}

	public function test_payout_transitions(): void {
		self::assertTrue( PayoutStatus::canTransition( PayoutStatus::REQUESTED, PayoutStatus::PROCESSING ) );
		self::assertTrue( PayoutStatus::canTransition( PayoutStatus::PROCESSING, PayoutStatus::PAID ) );
		self::assertFalse( PayoutStatus::canTransition( PayoutStatus::PAID, PayoutStatus::REQUESTED ) );
	}

	public function test_open_payouts_reserve_money(): void {
		self::assertSame( array( PayoutStatus::REQUESTED, PayoutStatus::PROCESSING ), PayoutStatus::open() );
	}
}
