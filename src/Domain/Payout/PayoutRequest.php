<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Payout;

use WooAffiliatePro\Domain\Money;

/**
 * اعتبارسنجی درخواست تسویه پیش از ثبت در دیتابیس.
 *
 * خطاها با پیام فارسی و کد مشخص برمی‌گردند تا هم در پنل نماینده
 * و هم در REST API قابل نمایش باشند.
 */
final class PayoutRequest {

	public const ERROR_NEGATIVE      = 'payout_negative';
	public const ERROR_BELOW_MINIMUM = 'payout_below_minimum';
	public const ERROR_INSUFFICIENT  = 'payout_insufficient';
	public const ERROR_INVALID_METHOD = 'payout_invalid_method';

	public const METHODS = array( 'card', 'bank', 'wallet' );

	/** @var Money */
	private $amount;

	/** @var string */
	private $method;

	/** @var array<string, string> */
	private $errors = array();

	public function __construct( Money $amount, string $method ) {
		$this->amount = $amount;
		$this->method = $method;
	}

	/**
	 * @return array<string, string>
	 */
	public function validate( Balance $balance, Money $minimum ): array {
		$this->errors = array();

		if ( $this->amount->isNegative() || $this->amount->isZero() ) {
			$this->errors[ self::ERROR_NEGATIVE ] = 'مبلغ درخواست باید بیشتر از صفر باشد.';
		}

		if ( $this->amount->lessThan( $minimum ) ) {
			$this->errors[ self::ERROR_BELOW_MINIMUM ] = sprintf(
				'حداقل مبلغ تسویه %s تومان است.',
				$minimum->format()
			);
		}

		if ( $this->amount->greaterThan( $balance->available() ) ) {
			$this->errors[ self::ERROR_INSUFFICIENT ] = sprintf(
				'موجودی قابل برداشت شما %s تومان است.',
				$balance->available()->format()
			);
		}

		if ( ! in_array( $this->method, self::METHODS, true ) ) {
			$this->errors[ self::ERROR_INVALID_METHOD ] = 'روش تسویه نامعتبر است.';
		}

		return $this->errors;
	}

	public function isValid( Balance $balance, Money $minimum ): bool {
		return empty( $this->validate( $balance, $minimum ) );
	}

	public function amount(): Money {
		return $this->amount;
	}

	public function method(): string {
		return $this->method;
	}
}
