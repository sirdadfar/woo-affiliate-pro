<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Order;

use WooAffiliatePro\Domain\Money;

/**
 * یک ردیف سفارش، فقط با اطلاعاتی که برای محاسبهٔ پورسانت لازم است.
 *
 * این کلاس عمداً از کلاس‌های ووکامرس ارث نمی‌برد؛ به همین دلیل بدون
 * نصب وردپرس هم قابل تست است.
 */
final class OrderLine {

	/** @var int */
	private $productId;

	/** @var string */
	private $name;

	/** @var int[] */
	private $categoryIds;

	/** @var Money مبلغ ردیف بعد از تخفیف، بدون مالیات */
	private $lineTotal;

	/** @var Money مالیات همین ردیف */
	private $lineTax;

	/** @var int */
	private $quantity;

	/**
	 * @param int[] $categoryIds
	 */
	public function __construct(
		int $productId,
		string $name,
		array $categoryIds,
		Money $lineTotal,
		Money $lineTax,
		int $quantity = 1
	) {
		if ( $quantity < 1 ) {
			throw new \InvalidArgumentException( 'تعداد در هر ردیف باید حداقل ۱ باشد.' );
		}
		if ( $lineTotal->isNegative() ) {
			throw new \InvalidArgumentException( 'مبلغ ردیف نمی‌تواند منفی باشد.' );
		}

		$this->productId   = $productId;
		$this->name        = $name;
		$this->categoryIds = array_values( array_unique( array_map( 'intval', $categoryIds ) ) );
		$this->lineTotal   = $lineTotal;
		$this->lineTax     = $lineTax;
		$this->quantity    = $quantity;
	}

	public function productId(): int {
		return $this->productId;
	}

	public function name(): string {
		return $this->name;
	}

	/**
	 * @return int[]
	 */
	public function categoryIds(): array {
		return $this->categoryIds;
	}

	public function lineTotal(): Money {
		return $this->lineTotal;
	}

	public function lineTax(): Money {
		return $this->lineTax;
	}

	public function quantity(): int {
		return $this->quantity;
	}

	public function hasCategory( int $categoryId ): bool {
		return in_array( $categoryId, $this->categoryIds, true );
	}
}
