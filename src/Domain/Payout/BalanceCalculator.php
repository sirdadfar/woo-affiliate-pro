<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Payout;

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Domain\Commission\Status as CommissionStatus;
use WooAffiliatePro\Domain\Money;

/**
 * محاسبهٔ موجودی نماینده از روی ردیف‌های خام دیتابیس.
 *
 * ورودی عمداً آرایه‌های ساده است (نه نتایج $wpdb) تا این کلاس بدون
 * وردپرس هم تست شود و منطق مالی در یک جا متمرکز بماند.
 */
final class BalanceCalculator {

	/**
	 * @param array<int, array{amount: int, status: string}>  $commissions
	 * @param array<int, array{amount: int, status: string}>  $payouts
	 */
	public function calculate(
		array $commissions,
		array $payouts,
		string $currency = 'IRR',
		int $decimals = 0
	): Balance {
		$earned    = Money::zero( $currency, $decimals );
		$reversed  = Money::zero( $currency, $decimals );
		$paid      = Money::zero( $currency, $decimals );
		$pending   = Money::zero( $currency, $decimals );

		foreach ( $commissions as $row ) {
			$amount = Money::of( (int) $row['amount'], $currency, $decimals );
			$status = (string) $row['status'];

			if ( in_array( $status, CommissionStatus::earning(), true ) ) {
				$earned = $earned->add( $amount );
			}

			if ( CommissionStatus::REVERSED === $status ) {
				$reversed = $reversed->add( $amount );
			}
		}

		foreach ( $payouts as $row ) {
			$amount = Money::of( (int) $row['amount'], $currency, $decimals );
			$status = (string) $row['status'];

			if ( PayoutStatus::PAID === $status ) {
				$paid = $paid->add( $amount );
			}

			if ( in_array( $status, PayoutStatus::open(), true ) ) {
				$pending = $pending->add( $amount );
			}
		}

		return new Balance( $earned, $paid, $pending, $reversed );
	}
}
