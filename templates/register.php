<?php
/**
 * فرم ثبت‌نام نماینده.
 *
 * @var string                $notice پیام خطای کلی؛ فقط وقتی خطای فیلد وجود دارد.
 * @var array<string, string> $errors خطاهای فیلدبه‌فیلد برای نمایش کنار هر فیلد.
 * @var array<string, string> $values مقادیر قبلی فرم، برای حفظ ورودی بعد از خطا.
 *
 * @package WooAffiliatePro
 */

defined( 'ABSPATH' ) || exit;

$currentUser = wp_get_current_user();

$errors = isset( $errors ) && is_array( $errors ) ? $errors : array();
$values = isset( $values ) && is_array( $values ) ? $values : array();
$notice = isset( $notice ) ? (string) $notice : '';

$nameError   = isset( $errors['wap_name'] ) ? (string) $errors['wap_name'] : '';
$mobileError = isset( $errors['wap_mobile'] ) ? (string) $errors['wap_mobile'] : '';
$hasErrors   = ! empty( $errors );

$nameValue    = isset( $values['wap_name'] ) && '' !== (string) $values['wap_name']
	? (string) $values['wap_name']
	: (string) $currentUser->display_name;
$mobileValue  = isset( $values['wap_mobile'] ) ? (string) $values['wap_mobile'] : '';
$methodValue  = isset( $values['wap_payout_method'] ) ? (string) $values['wap_payout_method'] : 'card';
$detailsValue = isset( $values['wap_payout_details'] ) ? (string) $values['wap_payout_details'] : '';

$nameDescribedBy   = 'wap_name-hint' . ( '' !== $nameError ? ' wap_name-error' : '' );
$mobileDescribedBy = 'wap_mobile-hint' . ( '' !== $mobileError ? ' wap_mobile-error' : '' );
?>
<div class="wap-panel wap-auth" dir="rtl">
	<aside class="wap-auth__aside">
		<span class="wap-auth__mark">
			<svg xmlns="http://www.w3.org/2000/svg" width="28" height="28" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19" />
				<circle cx="10" cy="8" r="3.2" />
				<path d="M20 19v-1.4a3.4 3.4 0 0 0-2.6-3.3" />
				<path d="M15.5 5.2a3.2 3.2 0 0 1 0 5.9" />
			</svg>
		</span>
		<p class="wap-auth__kicker">برنامهٔ همکاری در فروش</p>
		<ul class="wap-auth__list">
			<li>
				<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M10.2 13.8a3.5 3.5 0 0 0 5 0l2.6-2.6a3.5 3.5 0 0 0-5-5l-1.4 1.4" />
					<path d="M13.8 10.2a3.5 3.5 0 0 0-5 0l-2.6 2.6a3.5 3.5 0 0 0 5 5l1.4-1.4" />
				</svg>
				کد معرف ۸ کاراکتری و لینک اختصاصی
			</li>
			<li>
				<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<ellipse cx="12" cy="7" rx="6.5" ry="3" />
					<path d="M5.5 7v4c0 1.66 2.91 3 6.5 3s6.5-1.34 6.5-3V7" />
					<path d="M5.5 11v4c0 1.66 2.91 3 6.5 3s6.5-1.34 6.5-3v-4" />
				</svg>
				پورسانت از هر خرید منتسب‌شده
			</li>
			<li>
				<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M12 3.8l7 2.4v5.3c0 4-2.9 6.9-7 8.7-4.1-1.8-7-4.7-7-8.7V6.2z" />
					<path d="M9.2 12l2 2 3.6-3.7" />
				</svg>
				درخواست تسویه از همان پنل
			</li>
		</ul>
		<p class="wap-auth__foot">اطلاعات پرداخت شما فقط برای واریز پورسانت استفاده می‌شود.</p>
	</aside>

	<div class="wap-auth__body">
		<p class="wap-eyebrow">
			<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<circle cx="12" cy="12" r="8.2" />
				<path d="M12 11v5" />
				<circle cx="12" cy="8.2" r=".9" fill="currentColor" stroke="none" />
			</svg>
			ثبت‌نام نماینده
		</p>
		<h2 class="wap-h2">همکاری در فروش</h2>
		<p class="wap-lede">
			با معرفی محصولات ما به دوستان و مخاطبانتان، از هر فروش پورسانت بگیرید.
			لینک اختصاصی شما پس از ثبت‌نام ساخته می‌شود و هر خرید از طریق آن به نام شما ثبت خواهد شد.
		</p>

		<?php if ( $hasErrors ) : ?>
			<div class="wap-alert wap-alert--error wap-errorsummary" role="alert" tabindex="-1" data-wap-error-summary aria-labelledby="wap-errorsummary-title">
				<span class="wap-alert__icon" aria-hidden="true">
					<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
						<circle cx="12" cy="12" r="8.2" />
						<path d="M9.2 9.2l5.6 5.6M14.8 9.2l-5.6 5.6" />
					</svg>
				</span>
				<div class="wap-alert__body">
					<p class="wap-alert__title" id="wap-errorsummary-title"><?php echo esc_html( '' !== $notice ? $notice : 'لطفاً خطاهای زیر را برطرف کنید.' ); ?></p>
					<ul class="wap-errorsummary__list">
						<?php foreach ( $errors as $field => $message ) : ?>
							<li><a href="#<?php echo esc_attr( (string) $field ); ?>"><?php echo esc_html( (string) $message ); ?></a></li>
						<?php endforeach; ?>
					</ul>
				</div>
			</div>
		<?php endif; ?>

		<form method="post" class="wap-form wap-form--auth">
			<?php wp_nonce_field( 'wap_register', 'wap_register_nonce' ); ?>
			<input type="hidden" name="wap_action" value="register" />

			<div class="wap-field<?php echo '' !== $nameError ? ' wap-field--invalid' : ''; ?>">
				<label class="wap-field__label" for="wap_name">
					<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
						<circle cx="12" cy="8" r="3.4" />
						<path d="M5.2 19.2a6.8 6.8 0 0 1 13.6 0" />
					</svg>
					نام و نام خانوادگی
				</label>
				<input class="wap-input" type="text" id="wap_name" name="wap_name" required
					value="<?php echo esc_attr( $nameValue ); ?>"
					autocomplete="name"
					aria-describedby="<?php echo esc_attr( $nameDescribedBy ); ?>"
					<?php if ( '' !== $nameError ) : ?>aria-invalid="true"<?php endif; ?> />
				<p class="wap-field__hint" id="wap_name-hint">همان نامی که در حساب کاربری ووکامرس شما ثبت شده است.</p>
				<?php if ( '' !== $nameError ) : ?>
					<p class="wap-field__error" id="wap_name-error">
						<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
							<circle cx="12" cy="12" r="8.2" />
							<path d="M12 7.8v5" />
							<circle cx="12" cy="16.2" r=".9" fill="currentColor" stroke="none" />
						</svg>
						<?php echo esc_html( $nameError ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="wap-field<?php echo '' !== $mobileError ? ' wap-field--invalid' : ''; ?>">
				<label class="wap-field__label" for="wap_mobile">
					<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
						<rect x="7" y="3.2" width="10" height="17.6" rx="2.4" />
						<path d="M10.6 17.6h2.8" />
					</svg>
					شمارهٔ موبایل
				</label>
				<input class="wap-input wap-input--ltr" type="tel" id="wap_mobile" name="wap_mobile" required
					value="<?php echo esc_attr( $mobileValue ); ?>"
					placeholder="09121234567" pattern="09[0-9]{9}" dir="ltr" inputmode="numeric" autocomplete="tel-national"
					aria-describedby="<?php echo esc_attr( $mobileDescribedBy ); ?>"
					<?php if ( '' !== $mobileError ) : ?>aria-invalid="true"<?php endif; ?> />
				<p class="wap-field__hint" id="wap_mobile-hint">۱۱ رقم، بدون فاصله یا خط تیره — مثل 09121234567.</p>
				<?php if ( '' !== $mobileError ) : ?>
					<p class="wap-field__error" id="wap_mobile-error">
						<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
							<circle cx="12" cy="12" r="8.2" />
							<path d="M12 7.8v5" />
							<circle cx="12" cy="16.2" r=".9" fill="currentColor" stroke="none" />
						</svg>
						<?php echo esc_html( $mobileError ); ?>
					</p>
				<?php endif; ?>
			</div>

			<div class="wap-field">
				<label class="wap-field__label" for="wap_payout_method">
					<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
						<rect x="3.5" y="6" width="17" height="12" rx="2.2" />
						<path d="M3.5 10h17" />
						<path d="M7 14.2h3" />
					</svg>
					روش دریافت پورسانت
				</label>
				<select class="wap-select" id="wap_payout_method" name="wap_payout_method" aria-describedby="wap_payout_method-hint">
					<option value="card"<?php echo 'card' === $methodValue ? ' selected="selected"' : ''; ?>>شماره کارت</option>
					<option value="bank"<?php echo 'bank' === $methodValue ? ' selected="selected"' : ''; ?>>شماره شبا</option>
					<option value="wallet"<?php echo 'wallet' === $methodValue ? ' selected="selected"' : ''; ?>>کیف پول</option>
				</select>
				<p class="wap-field__hint" id="wap_payout_method-hint">پورسانت از طریق همین روش برای شما واریز می‌شود.</p>
			</div>

			<div class="wap-field">
				<label class="wap-field__label" for="wap_payout_details">
					<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
						<path d="M4 8.5A2.5 2.5 0 0 1 6.5 6H18a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6.5A2.5 2.5 0 0 1 4 16.5z" />
						<path d="M4 8.5V7a2 2 0 0 1 2-2h10" />
						<circle cx="16" cy="12.5" r="1.2" />
					</svg>
					اطلاعات دریافت (شماره کارت / شبا)
				</label>
				<textarea class="wap-textarea wap-input--ltr" id="wap_payout_details" name="wap_payout_details" rows="2" dir="ltr"
					aria-describedby="wap_payout_details-hint"><?php echo esc_textarea( $detailsValue ); ?></textarea>
				<p class="wap-field__hint" id="wap_payout_details-hint">شماره کارت فقط برای واریز پورسانت استفاده می‌شود و در پنل شما ذخیره می‌گردد.</p>
			</div>

			<button type="submit" class="wap-btn wap-btn--gold wap-btn--block">
				<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M19 12H5" />
					<path d="M11 6l-6 6 6 6" />
				</svg>
				ثبت‌نام به‌عنوان نماینده
			</button>
		</form>
	</div>
</div>
