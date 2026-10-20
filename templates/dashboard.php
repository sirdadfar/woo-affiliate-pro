<?php
/**
 * پنل نماینده.
 *
 * @var array<string, mixed>  $affiliate
 * @var \WooAffiliatePro\Domain\Payout\Balance $balance
 * @var string $refUrl
 * @var int    $clicks
 * @var int    $clicks30
 * @var array<int, array<string, mixed>> $commissions
 * @var array<int, array<string, mixed>> $payouts
 * @var \WooAffiliatePro\Domain\Money $minPayout
 * @var string $currency
 * @var int    $cookieDays
 *
 * @package WooAffiliatePro
 */

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Domain\Commission\Status;
use WooAffiliatePro\Domain\Money;
use WooAffiliatePro\Domain\Payout\PayoutStatus;
use WooAffiliatePro\Support\PersianNumber;

$decimals   = isset( $decimals ) ? (int) $decimals : 0;
$cookieDays = isset( $cookieDays ) ? (int) $cookieDays : 30;

$statusLabels = array(
	Status::PENDING  => 'در انتظار تأیید',
	Status::APPROVED => 'تأیید شده',
	Status::PAID     => 'پرداخت شده',
	Status::REVERSED => 'برگشت خورده',
	Status::REJECTED => 'رد شده',
);
?>
<section class="wap-panel wap-dash" dir="rtl" aria-labelledby="wap-dash-title">
	<header class="wap-dash__head">
		<p class="wap-eyebrow">
			<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<path d="M4.5 19.5h15" />
				<path d="M7 19.5v-5M12 19.5V8M17 19.5v-8" />
			</svg>
			پنل نماینده
		</p>
		<h2 class="wap-h2" id="wap-dash-title">پنل همکاری در فروش</h2>
		<p class="wap-lede">موجودی، لینک اختصاصی و تاریخچهٔ پورسانت‌های شما در یک نگاه.</p>
	</header>

	<div class="wap-stats" role="group" aria-label="آمار حساب نمایندگی" aria-live="polite" aria-atomic="true">
		<article class="wap-stat wap-stat--gold">
			<span class="wap-stat__label">
				<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M4 8.5A2.5 2.5 0 0 1 6.5 6H18a2 2 0 0 1 2 2v9a2 2 0 0 1-2 2H6.5A2.5 2.5 0 0 1 4 16.5z" />
					<path d="M4 8.5V7a2 2 0 0 1 2-2h10" />
					<circle cx="16" cy="12.5" r="1.2" />
				</svg>
				موجودی قابل برداشت
			</span>
			<strong class="wap-stat__value"><?php echo esc_html( PersianNumber::money( $balance->available()->format() ) ); ?> <span class="wap-stat__unit"><?php echo esc_html( $currency ); ?></span></strong>
		</article>

		<article class="wap-stat">
			<span class="wap-stat__label">
				<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<ellipse cx="12" cy="7" rx="6.5" ry="3" />
					<path d="M5.5 7v4c0 1.66 2.91 3 6.5 3s6.5-1.34 6.5-3V7" />
					<path d="M5.5 11v4c0 1.66 2.91 3 6.5 3s6.5-1.34 6.5-3v-4" />
				</svg>
				مجموع پورسانت تأییدشده
			</span>
			<strong class="wap-stat__value"><?php echo esc_html( PersianNumber::money( $balance->earned()->format() ) ); ?> <span class="wap-stat__unit"><?php echo esc_html( $currency ); ?></span></strong>
		</article>

		<article class="wap-stat">
			<span class="wap-stat__label">
				<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M9 4.5l10 7-4.2 1.1-2.2 4.2z" />
					<path d="M12.3 13l4.2 4.2" />
					<circle cx="4.6" cy="4.6" r="1.6" />
				</svg>
				کلیک ۳۰ روز گذشته
			</span>
			<strong class="wap-stat__value"><?php echo esc_html( PersianNumber::digits( number_format( $clicks30 ) ) ); ?></strong>
			<span class="wap-stat__foot">کل: <?php echo esc_html( PersianNumber::digits( number_format( $clicks ) ) ); ?></span>
		</article>
	</div>

	<section class="wap-section" aria-labelledby="wap-ref-title">
		<h3 class="wap-h3" id="wap-ref-title">
			<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<path d="M10.2 13.8a3.5 3.5 0 0 0 5 0l2.6-2.6a3.5 3.5 0 0 0-5-5l-1.4 1.4" />
				<path d="M13.8 10.2a3.5 3.5 0 0 0-5 0l-2.6 2.6a3.5 3.5 0 0 0 5 5l1.4-1.4" />
			</svg>
			لینک اختصاصی شما
		</h3>

		<div class="wap-ref">
			<label class="wap-sr" for="wap_ref_url">لینک اختصاصی شما</label>
			<input class="wap-input wap-input--ltr wap-ref__input" type="text" id="wap_ref_url" readonly
				value="<?php echo esc_attr( $refUrl ); ?>" data-wap-select dir="ltr" />
			<button type="button" class="wap-iconbtn" data-wap-copy="#wap_ref_url" data-wap-copy-status="#wap_copy_status" aria-label="رونوشت لینک اختصاصی">
				<svg xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<rect x="8.5" y="8.5" width="10" height="10" rx="2.2" />
					<path d="M15.5 8.5V6.2A1.7 1.7 0 0 0 13.8 4.5H6.2A1.7 1.7 0 0 0 4.5 6.2v7.6a1.7 1.7 0 0 0 1.7 1.7h2.3" />
				</svg>
			</button>
			<code class="wap-code"><?php echo esc_html( $affiliate['code'] ); ?></code>
			<span class="wap-sr" id="wap_copy_status" role="status" aria-live="polite"></span>
		</div>
		<p class="wap-muted wap-small">
			هر خریدار که از این لینک وارد شود تا <?php echo esc_html( PersianNumber::digits( (string) $cookieDays ) ); ?> روز به شما نسبت داده می‌شود، حتی اگر همان روز خرید نکند.
		</p>
	</section>

	<section class="wap-section" aria-labelledby="wap-payout-title">
		<h3 class="wap-h3" id="wap-payout-title">
			<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<rect x="3.5" y="6" width="17" height="12" rx="2.2" />
				<path d="M3.5 10h17" />
				<path d="M7 14.2h3" />
			</svg>
			درخواست تسویه
		</h3>
		<?php if ( $balance->available()->compare( $minPayout ) >= 0 ) : ?>
			<form method="post" class="wap-form wap-payout">
				<?php wp_nonce_field( 'wap_payout', 'wap_payout_nonce' ); ?>
				<input type="hidden" name="wap_action" value="payout" />
				<div class="wap-payout__row">
					<div class="wap-field">
						<label class="wap-field__label" for="wap_amount">مبلغ (<?php echo esc_html( $currency ); ?>)</label>
						<input class="wap-input wap-input--ltr" type="text" id="wap_amount" name="wap_amount" dir="ltr"
							inputmode="decimal" autocomplete="off" aria-describedby="wap_amount-hint"
							value="<?php echo esc_attr( number_format( $balance->available()->toFloat(), $decimals, '.', '' ) ); ?>" />
					</div>
					<button type="submit" class="wap-btn wap-btn--gold">ثبت درخواست</button>
				</div>
				<p class="wap-field__hint" id="wap_amount-hint">حداقل مبلغ تسویه: <?php echo esc_html( PersianNumber::money( $minPayout->format() ) ); ?> <?php echo esc_html( $currency ); ?></p>
			</form>
		<?php else : ?>
			<p class="wap-note">
				<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<circle cx="12" cy="12" r="8.2" />
					<path d="M12 7.6V12l2.8 1.7" />
				</svg>
				<span>
					حداقل مبلغ تسویه <?php echo esc_html( PersianNumber::money( $minPayout->format() ) ); ?> <?php echo esc_html( $currency ); ?> است.
					وقتی موجودی شما به این مقدار رسید، فرم درخواست فعال می‌شود.
				</span>
			</p>
		<?php endif; ?>
	</section>

	<section class="wap-section" aria-labelledby="wap-commissions-title">
		<h3 class="wap-h3" id="wap-commissions-title">
			<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
				<path d="M4 19.5h16" />
				<path d="M7 19.5v-5M12 19.5V8M17 19.5v-8" />
			</svg>
			آخرین پورسانت‌ها
		</h3>

		<div class="wap-table-wrap">
			<table class="wap-table">
				<caption class="wap-sr">آخرین پورسانت‌های شما</caption>
				<thead>
					<tr>
						<th scope="col">سفارش</th>
						<th scope="col">مبلغ</th>
						<th scope="col">وضعیت</th>
						<th scope="col">تاریخ</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $commissions ) ) : ?>
					<tr>
						<td colspan="4">
							<div class="wap-empty">
								<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
									<path d="M4.5 12.5l2.4-6.3A2 2 0 0 1 8.8 5h6.4a2 2 0 0 1 1.9 1.2l2.4 6.3" />
									<path d="M4.5 12.5h3.7l1 2.2h5.6l1-2.2h3.7v4.3a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2z" />
								</svg>
								<p class="wap-empty__title">هنوز پورسانتی ثبت نشده است.</p>
								<p class="wap-empty__hint">لینک اختصاصی را با مخاطبانتان به اشتراک بگذارید تا اولین پورسانت ساخته شود.</p>
							</div>
						</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $commissions as $row ) : ?>
						<tr>
							<td data-label="سفارش"><span class="wap-mono">#<?php echo esc_html( PersianNumber::digits( (string) $row['order_id'] ) ); ?></span></td>
							<td data-label="مبلغ" class="wap-num"><?php echo esc_html( PersianNumber::money( Money::of( (int) $row['amount'], $currency, $decimals )->format() ) ); ?> <?php echo esc_html( $currency ); ?></td>
							<td data-label="وضعیت"><span class="wap-badge wap-badge--<?php echo esc_attr( (string) $row['status'] ); ?>">
								<?php echo esc_html( $statusLabels[ (string) $row['status'] ] ?? (string) $row['status'] ); ?>
							</span></td>
							<td data-label="تاریخ" class="wap-num"><?php echo esc_html( PersianNumber::digits( mysql2date( 'Y/m/d', (string) $row['created_at'] ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
	</section>

	<?php if ( ! empty( $payouts ) ) : ?>
		<section class="wap-section" aria-labelledby="wap-payouts-title">
			<h3 class="wap-h3" id="wap-payouts-title">
				<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="20" height="20" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">
					<path d="M4 9.5h16" />
					<path d="M12 4l8 5.5H4z" />
					<path d="M6.5 9.5V17M12 9.5V17M17.5 9.5V17" />
					<path d="M4 19.5h16" />
				</svg>
				درخواست‌های تسویه
			</h3>
			<div class="wap-table-wrap">
				<table class="wap-table">
					<caption class="wap-sr">تاریخچهٔ درخواست‌های تسویه</caption>
					<thead>
						<tr>
							<th scope="col">مبلغ</th>
							<th scope="col">وضعیت</th>
							<th scope="col">تاریخ درخواست</th>
						</tr>
					</thead>
					<tbody>
					<?php foreach ( $payouts as $row ) : ?>
						<tr>
							<td data-label="مبلغ" class="wap-num"><?php echo esc_html( PersianNumber::money( Money::of( (int) $row['amount'], $currency, $decimals )->format() ) ); ?> <?php echo esc_html( $currency ); ?></td>
							<td data-label="وضعیت"><span class="wap-badge wap-badge--<?php echo esc_attr( (string) $row['status'] ); ?>"><?php echo esc_html( PayoutStatus::label( (string) $row['status'] ) ); ?></span></td>
							<td data-label="تاریخ درخواست" class="wap-num"><?php echo esc_html( PersianNumber::digits( mysql2date( 'Y/m/d', (string) $row['requested_at'] ) ) ); ?></td>
						</tr>
					<?php endforeach; ?>
					</tbody>
				</table>
			</div>
		</section>
	<?php endif; ?>
</section>
