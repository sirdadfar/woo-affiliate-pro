<?php
declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * فایل‌های src با گارد `defined( 'ABSPATH' ) || exit` محافظت شده‌اند.
 * در تست‌ها وردپرس بارگذاری نمی‌شود، پس فقط برای همین گارد مقدار
 * ریشهٔ پروژه را تعریف می‌کنیم؛ هیچ تابع وردپرسی شبیه‌سازی نمی‌شود.
 */
if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', dirname( __DIR__ ) . '/' );
}

/**
 * در تست‌های واحد هیچ نیازی به وردپرس نیست؛ تمام کلاس‌های دامنه
 * مستقل از WP نوشته شده‌اند و همین، دلیل اصلی تست‌پذیر بودنشان است.
 */
define( 'WAP_TESTS', true );
