<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Referral;

/**
 * ساخت و اعتبارسنجی کد معرف.
 *
 * حروف مبهم (0/O و 1/I/L) حذف شده‌اند تا کدی که نماینده روی کارت ویزیت
 * یا در پیام می‌فرستد، با اشتباه خواندن، به کد دیگری تبدیل نشود.
 */
final class CodeGenerator {

	/** بدون 0، O، 1، I، L */
	public const ALPHABET = '23456789ABCDEFGHJKMNPQRSTUVWXYZ';

	public const MIN_LENGTH = 4;
	public const MAX_LENGTH = 32;

	/** @var callable|null تابعی که می‌گوید کد قبلاً استفاده شده یا نه */
	private $exists;

	/**
	 * @param callable|null $exists function(string $code): bool
	 */
	public function __construct( ?callable $exists = null ) {
		$this->exists = $exists;
	}

	/**
	 * @param int $length
	 *
	 * @throws \RuntimeException وقتی بعد از چند تلاش کد یکتا پیدا نشود.
	 */
	public function generate( int $length = 8 ): string {
		if ( $length < self::MIN_LENGTH || $length > self::MAX_LENGTH ) {
			throw new \InvalidArgumentException(
				sprintf( 'طول کد باید بین %d و %d باشد.', self::MIN_LENGTH, self::MAX_LENGTH )
			);
		}

		$alphabetLength = strlen( self::ALPHABET );

		for ( $attempt = 0; $attempt < 20; $attempt++ ) {
			$code = '';
			for ( $i = 0; $i < $length; $i++ ) {
				$code .= self::ALPHABET[ random_int( 0, $alphabetLength - 1 ) ];
			}
			if ( null === $this->exists || ! call_user_func( $this->exists, $code ) ) {
				return $code;
			}
		}

		throw new \RuntimeException( 'ساخت کد یکتا پس از ۲۰ تلاش ممکن نشد؛ طول کد را بیشتر کنید.' );
	}

	/**
	 * کد ورودی کاربر را نرمال می‌کند: حذف فاصله، خط تیره و تبدیل به بزرگ.
	 */
	public static function normalize( string $code ): string {
		$code = strtoupper( trim( $code ) );
		$code = str_replace( array( ' ', '-', '_', '.', '/', "\u{200c}" ), '', $code );

		// تبدیل ارقام فارسی/عربی به لاتین، چون کاربر ممکن است کد را فارسی تایپ کند.
		$code = strtr(
			$code,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);

		return $code;
	}

	/**
	 * فقط حروف، ارقام، فاصله و جداکننده‌های رایج پذیرفته می‌شوند؛
	 * هر کاراکتر دیگری (اسلش، کوتیشن، نقطه‌ویرگول، تگ) پیش از نرمال‌سازی رد می‌شود
	 * تا ورودی بتواند به تزریق در کوکی یا کوئری استرینگ تبدیل شود.
	 */
	public static function isValid( string $code ): bool {
		$raw = trim( $code );

		if ( '' === $raw ) {
			return false;
		}

		if ( ! preg_match( '/^[A-Za-z0-9\s\-_.۰-۹٠-٩]+$/u', $raw ) ) {
			return false;
		}

		$normalized = self::normalize( $raw );

		return (bool) preg_match( '/^[A-Z0-9]{' . self::MIN_LENGTH . ',' . self::MAX_LENGTH . '}$/', $normalized );
	}

	/**
	 * کد را برای استفاده در URL آماده می‌کند.
	 */
	public static function toUrlParam( string $code, string $baseUrl, string $param = 'ref' ): string {
		$separator = false === strpos( $baseUrl, '?' ) ? '?' : '&';

		return $baseUrl . $separator . rawurlencode( $param ) . '=' . rawurlencode( self::normalize( $code ) );
	}
}
