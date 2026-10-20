<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WooAffiliatePro\Support\PersianNumber;

/**
 * @covers \WooAffiliatePro\Support\PersianNumber
 */
final class PersianNumberTest extends TestCase {

	public function test_digits_only_replaces_latin_digits(): void {
		self::assertSame( '۰۱۲۳۴۵۶۷۸۹', PersianNumber::digits( '0123456789' ) );
		self::assertSame( 'سفارش #۱۲۳ — ۱۴۰۲', PersianNumber::digits( 'سفارش #123 — 1402' ) );
		self::assertSame( 'بدون عدد', PersianNumber::digits( 'بدون عدد' ) );
	}

	public function test_money_converts_separators_and_digits(): void {
		self::assertSame( '۱٬۲۳۴٬۵۶۷', PersianNumber::money( '1,234,567' ) );
		self::assertSame( '۱۲٬۵۰۰٫۵۰', PersianNumber::money( '12,500.50' ) );
		self::assertSame( '۰', PersianNumber::money( '0' ) );
	}

	public function test_format_uses_persian_decimal_and_group_separators(): void {
		self::assertSame( '۱٬۲۳۴٬۵۶۷', PersianNumber::format( 1234567 ) );
		self::assertSame( '۱۲٫۵', PersianNumber::format( 12.5, 1 ) );
	}

	public function test_negative_decimals_are_clamped(): void {
		self::assertSame( '۱۲', PersianNumber::format( 12.4, -3 ) );
	}
}
