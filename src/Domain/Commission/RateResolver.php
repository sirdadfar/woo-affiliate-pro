<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Commission;

use WooAffiliatePro\Domain\Money;
use WooAffiliatePro\Domain\Order\OrderLine;

/**
 * انتخاب نرخ مناسب برای هر ردیف سفارش.
 *
 * ترتیب اولویت:
 *   ۱) استثنای محصول (پورسانت صفر)
 *   ۲) استثنای دسته‌بندی (پورسانت صفر)
 *   ۳) نرخ اختصاصی دسته‌بندی
 *   ۴) نرخ پیش‌فرض سیاست
 *
 * اگر یک محصول در چند دسته با نرخ‌های مختلف باشد، نرخ *کمترین شناسهٔ دسته*
 * انتخاب می‌شود تا نتیجه در اجراهای مختلف یکسان باشد (deterministic).
 */
final class RateResolver {

	/** @var CommissionPolicy */
	private $policy;

	public function __construct( CommissionPolicy $policy ) {
		$this->policy = $policy;
	}

	/**
	 * @return array{rate_type: string, basis_points: int, fixed: Money|null, reason: string}
	 */
	public function resolveFor( OrderLine $line ): array {
		if ( in_array( $line->productId(), $this->policy->excludeProductIds(), true ) ) {
			return $this->zero( 'محصول از پورسانت مستثنا شده است.' );
		}

		foreach ( $line->categoryIds() as $categoryId ) {
			if ( in_array( $categoryId, $this->policy->excludeCategoryIds(), true ) ) {
				return $this->zero( 'دسته‌بندی محصول از پورسانت مستثنا شده است.' );
			}
		}

		$categoryRates = $this->policy->categoryRates();
		$matching      = array();
		foreach ( $line->categoryIds() as $categoryId ) {
			if ( isset( $categoryRates[ $categoryId ] ) ) {
				$matching[ $categoryId ] = $categoryRates[ $categoryId ];
			}
		}

		if ( ! empty( $matching ) ) {
			ksort( $matching );
			$chosenId = (int) array_key_first( $matching );
			$rate     = $matching[ $chosenId ];

			if ( CommissionPolicy::TYPE_FIXED === $rate['type'] ) {
				return array(
					'rate_type'   => CommissionPolicy::TYPE_FIXED,
					'basis_points' => 0,
					'fixed'       => Money::of( (int) $rate['value'], $line->lineTotal()->currency(), $line->lineTotal()->decimals() ),
					'reason'      => sprintf( 'نرخ ثابت دستهٔ %d', $chosenId ),
				);
			}

			return array(
				'rate_type'   => CommissionPolicy::TYPE_PERCENT,
				'basis_points' => (int) $rate['value'],
				'fixed'       => null,
				'reason'      => sprintf( 'نرخ اختصاصی دستهٔ %d', $chosenId ),
			);
		}

		if ( $this->policy->isPercent() ) {
			return array(
				'rate_type'   => CommissionPolicy::TYPE_PERCENT,
				'basis_points' => $this->policy->rateBasisPoints(),
				'fixed'       => null,
				'reason'      => 'نرخ پیش‌فرض',
			);
		}

		return array(
			'rate_type'   => CommissionPolicy::TYPE_FIXED,
			'basis_points' => 0,
			'fixed'       => $this->policy->fixedAmount(),
			'reason'      => 'مبلغ ثابت پیش‌فرض',
		);
	}

	/**
	 * @return array{rate_type: string, basis_points: int, fixed: null, reason: string}
	 */
	private function zero( string $reason ): array {
		return array(
			'rate_type'   => CommissionPolicy::TYPE_PERCENT,
			'basis_points' => 0,
			'fixed'       => null,
			'reason'      => $reason,
		);
	}
}
