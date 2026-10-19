<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Commission;

use WooAffiliatePro\Domain\Money;
use WooAffiliatePro\Domain\Order\OrderSnapshot;

/**
 * محاسبه‌گر پورسانت — قلب افزونه و تنها بخشی که باید بی‌عیب باشد،
 * چون هر خطایش مستقیماً پول یک نفر است.
 *
 * قواعد:
 *   - مبنا به‌صورت پیش‌فرض مبلغ ردیف بعد از تخفیف است (بدون مالیات و ارسال).
 *   - مالیات و هزینهٔ ارسال فقط اگر در سیاست روشن شده باشند اضافه می‌شوند.
 *   - «مبلغ ثابت» یک‌بار برای هر سفارش اعمال می‌شود، نه برای هر ردیف.
 *   - سقف پورسانت با یک سطر تعدیل منفی اعمال می‌شود تا جمع سطرها همیشه
 *     با مبلغ نهایی برابر بماند.
 */
final class Calculator {

	/** @var RateResolver */
	private $resolver;

	public function __construct( ?RateResolver $resolver = null ) {
		$this->resolver = $resolver;
	}

	public function calculate( OrderSnapshot $order, CommissionPolicy $policy ): CommissionBreakdown {
		$resolver = $this->resolver ?: new RateResolver( $policy );

		if ( $order->isEmpty() ) {
			return CommissionBreakdown::zero( $order->currency(), $order->decimals() );
		}

		$lines         = array();
		$notes         = array();
		$fixedApplied  = false;
		$total         = Money::zero( $order->currency(), $order->decimals() );
		$zero          = Money::zero( $order->currency(), $order->decimals() );

		foreach ( $order->lines() as $line ) {
			$resolved = $resolver->resolveFor( $line );
			$base     = $line->lineTotal();

			if ( $policy->includesTax() ) {
				$base = $base->add( $line->lineTax() );
			}

			if ( CommissionPolicy::TYPE_FIXED === $resolved['rate_type'] ) {
				if ( $fixedApplied || null === $resolved['fixed'] ) {
					continue;
				}
				$fixedApplied = true;
				$amount       = $resolved['fixed'];
				$lines[]      = new CommissionLine(
					$line->name(),
					$zero,
					0,
					$amount,
					$resolved['reason'] . ' (یک‌بار برای هر سفارش)',
					$line->productId()
				);
				$total        = $total->add( $amount );
				continue;
			}

			$basisPoints = $resolved['basis_points'];
			if ( 0 === $basisPoints ) {
				$notes[] = sprintf( 'ردیف «%s» پورسانت ندارد: %s', $line->name(), $resolved['reason'] );
				continue;
			}

			$amount  = $base->percent( $basisPoints );
			$lines[] = new CommissionLine(
				$line->name(),
				$base,
				$basisPoints,
				$amount,
				$resolved['reason'],
				$line->productId()
			);
			$total   = $total->add( $amount );
		}

		// هزینهٔ ارسال، با نرخ پیش‌فرض سیاست (نه نرخ دسته‌بندی).
		if ( $policy->includesShipping() && ! $order->shipping()->isZero() ) {
			if ( $policy->isPercent() ) {
				$amount = $order->shipping()->percent( $policy->rateBasisPoints() );
				if ( ! $amount->isZero() ) {
					$lines[] = new CommissionLine(
						'هزینهٔ ارسال',
						$order->shipping(),
						$policy->rateBasisPoints(),
						$amount,
						'ارسال در محاسبه لحاظ می‌شود'
					);
					$total   = $total->add( $amount );
				}
			}
		}

		$capped = false;
		$max    = $policy->maxCommission();
		if ( null !== $max && $total->greaterThan( $max ) ) {
			$adjustment = $max->subtract( $total ); // منفی
			$lines[]    = new CommissionLine(
				'تعدیل سقف پورسانت',
				$total,
				0,
				$adjustment,
				'سقف پورسانت این نماینده اعمال شد'
			);
			$total  = $max;
			$capped = true;
			$notes[] = sprintf( 'پورسانت به سقف %s محدود شد.', $max->format() );
		}

		return new CommissionBreakdown( $lines, $total, $notes, $capped );
	}
}
