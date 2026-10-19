<?php
declare( strict_types=1 );

require_once dirname( __DIR__ ) . '/vendor/autoload.php';

/**
 * در تست‌های واحد هیچ نیازی به وردپرس نیست؛ تمام کلاس‌های دامنه
 * مستقل از WP نوشته شده‌اند و همین، دلیل اصلی تست‌پذیر بودنشان است.
 */
define( 'WAP_TESTS', true );
