<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Support;

/**
 * دسترسی تایپ‌دار به تنظیمات، با مقادیر پیش‌فرض در یک جا.
 *
 * همهٔ کلاس‌ها از این استفاده می‌کنند تا وقتی کلیدی در دیتابیس نیست،
 * رفتار برنامه قابل پیش‌بینی باشد و هر جا مجبور نشویم `?:` بنویسیم.
 */
final class Settings {

	public const OPTION = 'wap_settings';

	/**
	 * @return array<string, mixed>
	 */
	public static function defaults(): array {
		return array(
			'cookie_days'            => 30,
			'default_rate_bp'        => 1000, // ۱۰٪
			'rate_type'              => 'percent',
			'fixed_amount'           => 0,
			'include_shipping'       => false,
			'include_tax'            => false,
			'category_rates'         => array(), // [category_id => basis_points]
			'exclude_products'       => '',
			'exclude_categories'     => '',
			'max_commission'         => 0, // صفر = بدون سقف
			'min_payout'             => 100000,
			'approve_on'             => 'completed', // completed | processing
			'auto_approve'           => true,
			'dashboard_page_id'      => 0,
			'delete_data_on_uninstall' => false,
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	public static function all(): array {
		$stored = get_option( self::OPTION, array() );
		if ( ! is_array( $stored ) ) {
			$stored = array();
		}

		return array_merge( self::defaults(), $stored );
	}

	/**
	 * @return mixed
	 */
	public static function get( string $key ) {
		$all = self::all();

		return $all[ $key ] ?? null;
	}

	public static function int( string $key ): int {
		return (int) self::get( $key );
	}

	public static function bool( string $key ): bool {
		return (bool) self::get( $key );
	}

	public static function string( string $key ): string {
		return (string) self::get( $key );
	}

	/**
	 * @param array<string, mixed> $values
	 */
	public static function update( array $values ): void {
		$merged = array_merge( self::all(), $values );
		update_option( self::OPTION, $merged, false );
	}

	public static function cookieDays(): int {
		$days = self::int( 'cookie_days' );

		return max( 1, min( 365, $days ) );
	}

	/**
	 * فهرست شناسه‌های محصول/دسته از رشتهٔ جداشده با کاما.
	 *
	 * @return int[]
	 */
	public static function idList( string $key ): array {
		$raw = (string) self::get( $key );
		if ( '' === trim( $raw ) ) {
			return array();
		}

		$parts = preg_split( '/[\s,،]+/u', $raw ) ?: array();

		return array_values( array_filter( array_map( 'intval', $parts ) ) );
	}
}
