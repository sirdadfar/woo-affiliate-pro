<?php
/**
 * فرم ثبت‌نام نماینده.
 *
 * @package WooAffiliatePro
 */

defined( 'ABSPATH' ) || exit;

$currentUser = wp_get_current_user();
?>
<div class="wap-panel">
	<h2>همکاری در فروش</h2>
	<p class="wap-muted">
		با معرفی محصولات ما به دوستان و مخاطبانتان، از هر فروش پورسانت بگیرید.
		لینک اختصاصی شما پس از ثبت‌نام ساخته می‌شود و هر خرید از طریق آن به نام شما ثبت خواهد شد.
	</p>

	<form method="post" class="wap-form">
		<?php wp_nonce_field( 'wap_register', 'wap_register_nonce' ); ?>
		<input type="hidden" name="wap_action" value="register" />

		<label for="wap_name">نام و نام خانوادگی</label>
		<input type="text" id="wap_name" name="wap_name" required
			value="<?php echo esc_attr( $currentUser->display_name ); ?>" />

		<label for="wap_mobile">شمارهٔ موبایل</label>
		<input type="tel" id="wap_mobile" name="wap_mobile" required
			placeholder="09121234567" pattern="09[0-9]{9}" dir="ltr" />

		<label for="wap_payout_method">روش دریافت پورسانت</label>
		<select id="wap_payout_method" name="wap_payout_method">
			<option value="card">شماره کارت</option>
			<option value="bank">شماره شبا</option>
			<option value="wallet">کیف پول</option>
		</select>

		<label for="wap_payout_details">اطلاعات دریافت (شماره کارت / شبا)</label>
		<textarea id="wap_payout_details" name="wap_payout_details" rows="2" dir="ltr"></textarea>

		<p class="wap-muted wap-small">
			شماره کارت فقط برای واریز پورسانت استفاده می‌شود و در پنل شما ذخیره می‌گردد.
		</p>

		<button type="submit" class="button alt">ثبت‌نام به‌عنوان نماینده</button>
	</form>
</div>
