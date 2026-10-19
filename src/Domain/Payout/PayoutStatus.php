<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Payout;

/**
 * وضعیت درخواست تسویه.
 */
final class PayoutStatus {

	public const REQUESTED  = 'requested';
	public const PROCESSING = 'processing';
	public const PAID       = 'paid';
	public const REJECTED   = 'rejected';

	/** @var array<string, string[]> */
	private const TRANSITIONS = array(
		self::REQUESTED  => array( self::PROCESSING, self::PAID, self::REJECTED ),
		self::PROCESSING => array( self::PAID, self::REJECTED ),
		self::PAID       => array(),
		self::REJECTED   => array(),
	);

	/**
	 * درخواست‌هایی که پول را «رزرو» کرده‌اند ولی هنوز پرداخت نشده‌اند.
	 *
	 * @return string[]
	 */
	public static function open(): array {
		return array( self::REQUESTED, self::PROCESSING );
	}

	public static function canTransition( string $from, string $to ): bool {
		if ( $from === $to ) {
			return true;
		}
		if ( ! isset( self::TRANSITIONS[ $from ] ) ) {
			throw new \InvalidArgumentException( sprintf( 'وضعیت ناشناخته: %s', $from ) );
		}

		return in_array( $to, self::TRANSITIONS[ $from ], true );
	}

	public static function label( string $status ): string {
		$labels = array(
			self::REQUESTED  => 'در انتظار بررسی',
			self::PROCESSING => 'در حال پردازش',
			self::PAID       => 'پرداخت شده',
			self::REJECTED   => 'رد شده',
		);

		return $labels[ $status ] ?? $status;
	}
}
