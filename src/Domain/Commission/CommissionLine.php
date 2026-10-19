<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Commission;

use WooAffiliatePro\Domain\Money;

/**
 * یک سطر از ریز محاسبهٔ پورسانت.
 *
 * نگه داشتن «مبنای محاسبه» و «نرخ» در کنار «مبلغ» باعث می‌شود
 * نماینده بتواند ببیند پورسانتش از چه چیزی و با چه نرخی ساخته شده —
 * این تنها راه جلوگیری از تیکت‌های «پورسانتم کم شده».
 */
final class CommissionLine {

	/** @var string */
	private $label;

	/** @var Money */
	private $base;

	/** @var int */
	private $basisPoints;

	/** @var Money */
	private $amount;

	/** @var string */
	private $reason;

	/** @var int */
	private $productId;

	public function __construct( string $label, Money $base, int $basisPoints, Money $amount, string $reason, int $productId = 0 ) {
		$this->label       = $label;
		$this->base        = $base;
		$this->basisPoints = $basisPoints;
		$this->amount      = $amount;
		$this->reason      = $reason;
		$this->productId   = $productId;
	}

	public function label(): string {
		return $this->label;
	}

	public function base(): Money {
		return $this->base;
	}

	public function basisPoints(): int {
		return $this->basisPoints;
	}

	public function amount(): Money {
		return $this->amount;
	}

	public function reason(): string {
		return $this->reason;
	}

	public function productId(): int {
		return $this->productId;
	}
}
