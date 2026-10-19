<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Tests\Unit;

use PHPUnit\Framework\TestCase;
use WooAffiliatePro\Domain\Referral\CodeGenerator;

final class CodeGeneratorTest extends TestCase {

	public function test_generated_code_uses_safe_alphabet_only(): void {
		$generator = new CodeGenerator();

		for ( $i = 0; $i < 50; $i++ ) {
			$code = $generator->generate( 8 );

			self::assertSame( 8, strlen( $code ) );
			self::assertMatchesRegularExpression( '/^[23456789ABCDEFGHJKMNPQRSTUVWXYZ]{8}$/', $code );
			self::assertStringNotContainsString( '0', $code );
			self::assertStringNotContainsString( 'O', $code );
			self::assertStringNotContainsString( '1', $code );
		}
	}

	public function test_it_retries_when_code_already_exists(): void {
		$calls     = 0;
		$generator = new CodeGenerator(
			static function () use ( &$calls ): bool {
				$calls++;

				return $calls <= 2; // دو کد اول «تکراری» هستند
			}
		);

		$code = $generator->generate( 6 );

		self::assertSame( 6, strlen( $code ) );
		self::assertSame( 3, $calls );
	}

	public function test_it_gives_up_after_twenty_collisions(): void {
		$generator = new CodeGenerator( static function (): bool { return true; } );

		$this->expectException( \RuntimeException::class );

		$generator->generate( 8 );
	}

	public function test_rejects_too_short_or_too_long_length(): void {
		$generator = new CodeGenerator();

		$this->expectException( \InvalidArgumentException::class );
		$generator->generate( 2 );
	}

	public function test_rejects_too_long(): void {
		$generator = new CodeGenerator();

		$this->expectException( \InvalidArgumentException::class );
		$generator->generate( 40 );
	}

	public function test_normalize_handles_separators_and_case(): void {
		self::assertSame( 'AB12CD', CodeGenerator::normalize( ' ab-12_cd ' ) );
		self::assertSame( 'AB12CD', CodeGenerator::normalize( 'ab.12/cd' ) );
		self::assertSame( 'AB12', CodeGenerator::normalize( "ab\u{200c}12" ) );
	}

	public function test_normalize_converts_persian_digits(): void {
		self::assertSame( 'AB1234', CodeGenerator::normalize( 'AB۱۲۳۴' ) );
		self::assertSame( '9876', CodeGenerator::normalize( '٩٨٧٦' ) );
	}

	public function test_validates_legitimate_codes(): void {
		self::assertTrue( CodeGenerator::isValid( 'ACME2024' ) );
		self::assertTrue( CodeGenerator::isValid( 'ab12' ) );
		self::assertTrue( CodeGenerator::isValid( 'a-b-1-2' ) );
	}

	public function test_rejects_injection_and_markup_attempts(): void {
		self::assertFalse( CodeGenerator::isValid( '<script>' ) );
		self::assertFalse( CodeGenerator::isValid( 'AB12"; DROP TABLE' ) );
		self::assertFalse( CodeGenerator::isValid( 'AB@12' ) );
		self::assertFalse( CodeGenerator::isValid( '../../etc/passwd' ) );
		self::assertFalse( CodeGenerator::isValid( 'AB' ) );
		self::assertFalse( CodeGenerator::isValid( str_repeat( 'A', 40 ) ) );
	}

	public function test_spaces_are_accepted_and_stripped(): void {
		// کاربر ممکن است کد را با فاصله بفرستد؛ نرمال‌سازی آن را می‌پذیرد.
		self::assertTrue( CodeGenerator::isValid( 'AB 12' ) );
		self::assertSame( 'AB12', CodeGenerator::normalize( 'AB 12' ) );
	}

	public function test_url_param_appends_correctly(): void {
		self::assertSame(
			'https://shop.ir/?ref=AB12CD',
			CodeGenerator::toUrlParam( 'ab12cd', 'https://shop.ir/' )
		);

		self::assertSame(
			'https://shop.ir/product/x?color=red&ref=AB12CD',
			CodeGenerator::toUrlParam( 'AB12CD', 'https://shop.ir/product/x?color=red' )
		);
	}
}
