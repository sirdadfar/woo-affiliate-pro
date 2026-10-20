<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain;

defined( 'ABSPATH' ) || exit;

/**
 * مقدار پولی با عدد صحیح (کوچک‌ترین واحد پول) تا خطای شناور هرگز رخ ندهد.
 *
 * برای ریال ایران معمولاً decimals = 0 و برای تومان هم 0؛ اما همان API
 * برای ارزهایی با دو رقم اعشار هم کار می‌کند.
 *
 * چرا عدد صحیح؟ چون 0.1 + 0.2 در PHP برابر 0.30000000000000004 است و
 * در محاسبهٔ پورسانت، همین خطا در هزاران سفارش جمع می‌شود و حساب نماینده
 * با حساب فروشگاه نمی‌خواند.
 */
final class Money {

	/** @var int مبلغ به کوچک‌ترین واحد پول */
	private $amount;

	/** @var string */
	private $currency;

	/** @var int تعداد رقم اعشار ارز */
	private $decimals;

	private function __construct( int $amount, string $currency, int $decimals ) {
		$this->amount   = $amount;
		$this->currency = $currency;
		$this->decimals = $decimals;
	}

	public static function of( int $amount, string $currency = 'IRR', int $decimals = 0 ): self {
		if ( $decimals < 0 || $decimals > 4 ) {
			throw new \InvalidArgumentException( 'تعداد اعشار باید بین ۰ تا ۴ باشد.' );
		}

		return new self( $amount, strtoupper( $currency ), $decimals );
	}

	public static function zero( string $currency = 'IRR', int $decimals = 0 ): self {
		return new self( 0, strtoupper( $currency ), $decimals );
	}

	/**
	 * ساخت از عدد اعشاری ورودی (مثل مبلغ ووکامرس).
	 * گرد کردن نیم‌به‌بالا انجام می‌شود، نه گرد کردن بانکی.
	 */
	public static function fromFloat( float $value, string $currency = 'IRR', int $decimals = 0 ): self {
		$factor = 10 ** $decimals;

		return new self( (int) round( $value * $factor ), strtoupper( $currency ), $decimals );
	}

	public static function fromString( string $value, string $currency = 'IRR', int $decimals = 0 ): self {
		$normalized = str_replace( array( ',', ' ', "\u{200c}" ), '', $value );
		$normalized = strtr(
			$normalized,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);

		if ( ! is_numeric( $normalized ) ) {
			throw new \InvalidArgumentException( sprintf( 'مبلغ نامعتبر: %s', $value ) );
		}

		return self::fromFloat( (float) $normalized, $currency, $decimals );
	}

	public function amount(): int {
		return $this->amount;
	}

	public function currency(): string {
		return $this->currency;
	}

	public function decimals(): int {
		return $this->decimals;
	}

	public function add( self $other ): self {
		$this->assertSameCurrency( $other );

		return new self( $this->amount + $other->amount, $this->currency, $this->decimals );
	}

	public function subtract( self $other ): self {
		$this->assertSameCurrency( $other );

		return new self( $this->amount - $other->amount, $this->currency, $this->decimals );
	}

	public function multiply( int $factor ): self {
		return new self( $this->amount * $factor, $this->currency, $this->decimals );
	}

	/**
	 * درصد با واحد «پایهٔ یک‌صدم درصد» (basis point).
	 * ۱۰٪ = ۱۰۰۰ و ۲.۵٪ = ۲۵۰.
	 *
	 * برای جلوگیری از سرریز، مبلغ‌های بزرگ‌تر از PHP_INT_MAX/10000 رد می‌شوند
	 * تا نتیجه بی‌سروصدا اشتباه نشود.
	 */
	public function percent( int $basisPoints ): self {
		if ( $basisPoints < 0 ) {
			throw new \InvalidArgumentException( 'درصد منفی مجاز نیست.' );
		}
		if ( $this->amount > intdiv( PHP_INT_MAX, 10000 ) ) {
			throw new \OverflowException( 'مبلغ برای محاسبهٔ درصد بیش از حد بزرگ است.' );
		}

		$numerator = $this->amount * $basisPoints;

		// گرد کردن نیم‌به‌بالا برای مبالغ مثبت و نیم‌به‌پایین برای منفی.
		if ( $numerator >= 0 ) {
			$result = intdiv( $numerator + 5000, 10000 );
		} else {
			$result = -intdiv( -$numerator + 5000, 10000 );
		}

		return new self( $result, $this->currency, $this->decimals );
	}

	public function min( self $other ): self {
		$this->assertSameCurrency( $other );

		return $this->amount <= $other->amount ? $this : $other;
	}

	public function isZero(): bool {
		return 0 === $this->amount;
	}

	public function isNegative(): bool {
		return $this->amount < 0;
	}

	public function compare( self $other ): int {
		$this->assertSameCurrency( $other );

		return $this->amount <=> $other->amount;
	}

	public function equals( self $other ): bool {
		return $this->currency === $other->currency
			&& $this->decimals === $other->decimals
			&& $this->amount === $other->amount;
	}

	public function greaterThan( self $other ): bool {
		return $this->compare( $other ) > 0;
	}

	public function lessThan( self $other ): bool {
		return $this->compare( $other ) < 0;
	}

	public function toFloat(): float {
		return $this->amount / ( 10 ** $this->decimals );
	}

	public function format( string $thousandsSeparator = ',', string $decimalSeparator = '.' ): string {
		return number_format( $this->toFloat(), $this->decimals, $decimalSeparator, $thousandsSeparator );
	}

	public function __toString(): string {
		return $this->format() . ' ' . $this->currency;
	}

	private function assertSameCurrency( self $other ): void {
		if ( $this->currency !== $other->currency || $this->decimals !== $other->decimals ) {
			throw new \InvalidArgumentException(
				sprintf( 'واحد پول ناهمخوان: %s در برابر %s', $this->currency, $other->currency )
			);
		}
	}
}
