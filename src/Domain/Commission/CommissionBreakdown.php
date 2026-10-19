<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Commission;

use WooAffiliatePro\Domain\Money;

/**
 * نتیجهٔ کامل محاسبهٔ پورسانت یک سفارش.
 */
final class CommissionBreakdown {

	/** @var CommissionLine[] */
	private $lines;

	/** @var Money */
	private $total;

	/** @var string[] */
	private $notes;

	/** @var bool */
	private $capped;

	/**
	 * @param CommissionLine[] $lines
	 * @param string[]         $notes
	 */
	public function __construct( array $lines, Money $total, array $notes = array(), bool $capped = false ) {
		$this->lines  = array_values( $lines );
		$this->total  = $total;
		$this->notes  = array_values( $notes );
		$this->capped = $capped;
	}

	public static function zero( string $currency = 'IRR', int $decimals = 0 ): self {
		return new self( array(), Money::zero( $currency, $decimals ) );
	}

	/**
	 * @return CommissionLine[]
	 */
	public function lines(): array {
		return $this->lines;
	}

	public function total(): Money {
		return $this->total;
	}

	/**
	 * @return string[]
	 */
	public function notes(): array {
		return $this->notes;
	}

	public function isCapped(): bool {
		return $this->capped;
	}

	public function isEmpty(): bool {
		return $this->total->isZero();
	}

	/**
	 * خلاصهٔ قابل نمایش به نماینده.
	 */
	public function toArray(): array {
		$lines = array();
		foreach ( $this->lines as $line ) {
			$lines[] = array(
				'label'        => $line->label(),
				'product_id'   => $line->productId(),
				'base'         => $line->base()->amount(),
				'basis_points' => $line->basisPoints(),
				'amount'       => $line->amount()->amount(),
				'reason'       => $line->reason(),
			);
		}

		return array(
			'lines'  => $lines,
			'total'  => $this->total->amount(),
			'notes'  => $this->notes,
			'capped' => $this->capped,
		);
	}
}
