<?php
/**
 * پیام وضعیت پنل نماینده.
 *
 * @var string $message
 * @var string $type
 *
 * @package WooAffiliatePro
 */

defined( 'ABSPATH' ) || exit;

$type = in_array( $type, array( 'success', 'error', 'info' ), true ) ? $type : 'info';

// خطا با role="alert" فوراً خوانده می‌شود؛ پیام موفقیت/اطلاع با
// role="status" مؤدبانه و بدون قطع کردن کاربر اعلام می‌شود.
$wap_notice_role = 'error' === $type ? 'alert' : 'status';
?>
<div class="wap-notice wap-notice--<?php echo esc_attr( $type ); ?>" role="<?php echo esc_attr( $wap_notice_role ); ?>">
	<span class="wap-notice__icon" aria-hidden="true">
		<?php if ( 'success' === $type ) : ?>
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<circle cx="12" cy="12" r="8.2" />
				<path d="M8.6 12.2l2.3 2.3 4.5-4.6" />
			</svg>
		<?php elseif ( 'error' === $type ) : ?>
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<circle cx="12" cy="12" r="8.2" />
				<path d="M9.2 9.2l5.6 5.6M14.8 9.2l-5.6 5.6" />
			</svg>
		<?php else : ?>
			<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<circle cx="12" cy="12" r="8.2" />
				<path d="M12 11v5" />
				<circle cx="12" cy="8.2" r=".9" fill="currentColor" stroke="none" />
			</svg>
		<?php endif; ?>
	</span>
	<div class="wap-notice__body"><?php echo wp_kses_post( $message ); ?></div>
</div>
