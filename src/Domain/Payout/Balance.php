<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Payout;

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Domain\Money;

/**
 * وضعیت مالی یک نماینده در یک لحظه.
 *
 * تفکیک «درآمد تأییدشده» از «قابل برداشت» مهم است:
 * پورسانت تأییدشده‌ای که برایش درخواست تسویه ثبت شده، دیگر قابل برداشت نیست
 * ولی هنوز پرداخت نشده. اگر این دو یکی گرفته شوند، نماینده می‌تواند
 * دو بار برای همان پول درخواست بدهد.
 */
final class Balance {

	/** @var Money */
	private $earned;

	/** @var Money */
	private $paid;

	/** @var Money */
	private $pendingWithdrawals;

	/** @var Money */
	private $reversed;

	public function __construct( Money $earned, Money $paid, Money $pendingWithdrawals, Money $reversed ) {
		$this->earned             = $earned;
		$this->paid               = $paid;
		$this->pendingWithdrawals = $pendingWithdrawals;
		$this->reversed           = $reversed;
	}

	/** مجموع پورسانت‌های تأییدشده (شامل پرداخت‌شده‌ها) */
	public function earned(): Money {
		return $this->earned;
	}

	/** مجموع تسویه‌های انجام‌شده */
	public function paid(): Money {
		return $this->paid;
	}

	/** درخواست‌های تسویهٔ در جریان (ثبت‌شده ولی پرداخت‌نشده) */
	public function pendingWithdrawals(): Money {
		return $this->pendingWithdrawals;
	}

	/** پورسانت‌هایی که به دلیل برگشت سفارش یا تخلف باطل شدند */
	public function reversed(): Money {
		return $this->reversed;
	}

	/** مبلغی که الان می‌تواند درخواست تسویه بدهد */
	public function available(): Money {
		return $this->earned
			->subtract( $this->paid )
			->subtract( $this->pendingWithdrawals );
	}

	public function canWithdraw( Money $amount, Money $minimum ): bool {
		return ! $amount->isNegative()
			&& $amount->compare( $minimum ) >= 0
			&& $amount->compare( $this->available() ) <= 0;
	}

	public function toArray(): array {
		return array(
			'earned'              => $this->earned->amount(),
			'paid'                => $this->paid->amount(),
			'pending_withdrawals' => $this->pendingWithdrawals->amount(),
			'reversed'            => $this->reversed->amount(),
			'available'           => $this->available()->amount(),
			'currency'            => $this->earned->currency(),
		);
	}
}
