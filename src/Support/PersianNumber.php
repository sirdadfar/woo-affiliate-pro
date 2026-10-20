<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Support;

defined( 'ABSPATH' ) || exit;

/**
 * ارقام فارسی برای نمایش.
 *
 * چرا این کلاس؟ رابط کاربری فارسی است اما مبالغ، تعداد کلیک‌ها و تاریخ‌ها
 * از توابع PHP با ارقام لاتین می‌آیند. ترکیب «مبلغ لاتین» با «متن فارسی»
 * در یک کارت، ظاهر نیمه‌کاره می‌سازد. تبدیل فقط در لایهٔ نمایش انجام
 * می‌شود تا منطق مالی و تست‌های دامنه دست‌نخورده بمانند و مقدار
 * فیلدهای فرم (مثل مبلغ تسویه) همچنان لاتین بماند.
 */
final class PersianNumber {

	/**
	 * نگاشت ارقام لاتین به فارسی.
	 *
	 * @var array<string, string>
	 */
	private const DIGITS = array(
		'0' => '۰',
		'1' => '۱',
		'2' => '۲',
		'3' => '۳',
		'4' => '۴',
		'5' => '۵',
		'6' => '۶',
		'7' => '۷',
		'8' => '۸',
		'9' => '۹',
	);

	/**
	 * تبدیل ارقام یک رشته به فارسی؛ بقیهٔ کاراکترها دست‌نخورده می‌مانند.
	 */
	public static function digits( string $value ): string {
		return strtr( $value, self::DIGITS );
	}

	/**
	 * قالب‌بندی عدد با جداکنندهٔ هزارگان فارسی (٬) و اعشار فارسی (٫).
	 *
	 * @param int $decimals تعداد رقم اعشار.
	 */
	public static function format( float $amount, int $decimals = 0 ): string {
		return self::digits( number_format( $amount, max( 0, $decimals ), '٫', '٬' ) );
	}

	/**
	 * تبدیل خروجی آمادهٔ `Money::format()` به شکل فارسی.
	 *
	 * `Money::format()` از جداکنندهٔ لاتین (`,`) استفاده می‌کند و منطق دامنه
	 * نباید به‌خاطر نمایش تغییر کند؛ اینجا فقط جداکننده‌ها هم فارسی می‌شوند.
	 */
	public static function money( string $formatted ): string {
		return self::digits( str_replace( array( ',', '.' ), array( '٬', '٫' ), $formatted ) );
	}
}
