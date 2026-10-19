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
?>
<div class="wap-notice wap-notice--<?php echo esc_attr( $type ); ?>" role="status">
	<?php echo wp_kses_post( $message ); ?>
</div>
