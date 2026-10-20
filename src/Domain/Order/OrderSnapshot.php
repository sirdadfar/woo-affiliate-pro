<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Order;

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Domain\Money;

/**
 * تصویر تغییرناپذیر از یک سفارش در لحظهٔ محاسبهٔ پورسانت.
 */
final class OrderSnapshot {

	/** @var int */
	private $orderId;

	/** @var OrderLine[] */
	private $lines;

	/** @var Money */
	private $shipping;

	/** @var Money مجموع تخفیف سفارش (فقط برای گزارش، در محاسبه دوباره اعمال نمی‌شود) */
	private $discountTotal;

	/** @var string */
	private $currency;

	/** @var int */
	private $decimals;

	/**
	 * @param OrderLine[] $lines
	 */
	public function __construct(
		int $orderId,
		array $lines,
		Money $shipping,
		Money $discountTotal,
		string $currency = 'IRR',
		int $decimals = 0
	) {
		foreach ( $lines as $line ) {
			if ( ! $line instanceof OrderLine ) {
				throw new \InvalidArgumentException( 'همهٔ ردیف‌ها باید از نوع OrderLine باشند.' );
			}
		}

		$this->orderId       = $orderId;
		$this->lines         = array_values( $lines );
		$this->shipping      = $shipping;
		$this->discountTotal = $discountTotal;
		$this->currency      = $currency;
		$this->decimals      = $decimals;
	}

	public static function empty( int $orderId, string $currency = 'IRR', int $decimals = 0 ): self {
		return new self( $orderId, array(), Money::zero( $currency, $decimals ), Money::zero( $currency, $decimals ), $currency, $decimals );
	}

	public function orderId(): int {
		return $this->orderId;
	}

	/**
	 * @return OrderLine[]
	 */
	public function lines(): array {
		return $this->lines;
	}

	public function shipping(): Money {
		return $this->shipping;
	}

	public function discountTotal(): Money {
		return $this->discountTotal;
	}

	public function currency(): string {
		return $this->currency;
	}

	public function decimals(): int {
		return $this->decimals;
	}

	public function isEmpty(): bool {
		return 0 === count( $this->lines );
	}
}
