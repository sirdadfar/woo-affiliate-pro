<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Commission;

/**
 * چرخهٔ عمر پورسانت.
 *
 * pending  → ثبت شد ولی سفارش هنوز تحویل/تکمیل نشده
 * approved → قابل پرداخت
 * paid     → در تسویه حساب شد
 * reversed → سفارش برگشت خورد یا تخلف تأیید شد
 * rejected → مدیر دستی رد کرد
 */
final class Status {

	public const PENDING  = 'pending';
	public const APPROVED = 'approved';
	public const PAID     = 'paid';
	public const REVERSED = 'reversed';
	public const REJECTED = 'rejected';

	/** @var array<string, string[]> */
	private const TRANSITIONS = array(
		// «در انتظار» می‌تواند مستقیم برگشت بخورد: سفارش پیش از تکمیل لغو شده
		// و پورسانتی که هرگز تأیید نشده، باید باطل شود.
		self::PENDING => array( self::APPROVED, self::REJECTED, self::REVERSED ),
		self::APPROVED => array( self::PAID, self::REVERSED, self::REJECTED ),
		self::PAID => array( self::REVERSED ),
		self::REVERSED => array(),
		self::REJECTED => array( self::APPROVED ),
	);

	/**
	 * @return string[]
	 */
	public static function all(): array {
		return array( self::PENDING, self::APPROVED, self::PAID, self::REVERSED, self::REJECTED );
	}

	/**
	 * وضعیت‌هایی که در محاسبهٔ «موجودی قابل برداشت» مثبت حساب می‌شوند.
	 *
	 * @return string[]
	 */
	public static function earning(): array {
		return array( self::APPROVED, self::PAID );
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

	/**
	 * @return string[]
	 */
	public static function nextStates( string $from ): array {
		return self::TRANSITIONS[ $from ] ?? array();
	}

	public static function isFinal( string $status ): bool {
		return in_array( $status, array( self::REVERSED ), true );
	}

	public static function label( string $status ): string {
		$labels = array(
			self::PENDING  => 'در انتظار تأیید',
			self::APPROVED => 'تأیید شده',
			self::PAID     => 'پرداخت شده',
			self::REVERSED => 'برگشت خورده',
			self::REJECTED => 'رد شده',
		);

		return $labels[ $status ] ?? $status;
	}
}
