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
 *
 * @package WooAffiliatePro
 */

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Domain\Commission\Status;

$statusLabels = array(
	Status::PENDING  => 'در انتظار تأیید',
	Status::APPROVED => 'تأیید شده',
	Status::PAID     => 'پرداخت شده',
	Status::REVERSED => 'برگشت خورده',
	Status::REJECTED => 'رد شده',
);
?>
<div class="wap-panel">
	<h2>پنل همکاری در فروش</h2>

	<div class="wap-cards">
		<div class="wap-card">
			<span class="wap-card__label">موجودی قابل برداشت</span>
			<strong class="wap-card__value"><?php echo esc_html( number_format( $balance->available()->toFloat() ) ); ?> <?php echo esc_html( $currency ); ?></strong>
		</div>
		<div class="wap-card">
			<span class="wap-card__label">مجموع پورسانت تأییدشده</span>
			<strong class="wap-card__value"><?php echo esc_html( number_format( $balance->earned()->toFloat() ) ); ?> <?php echo esc_html( $currency ); ?></strong>
		</div>
		<div class="wap-card">
			<span class="wap-card__label">کلیک ۳۰ روز گذشته</span>
			<strong class="wap-card__value"><?php echo esc_html( number_format( $clicks30 ) ); ?></strong>
			<span class="wap-small wap-muted">کل: <?php echo esc_html( number_format( $clicks ) ); ?></span>
		</div>
	</div>

	<h3>لینک اختصاصی شما</h3>
	<div class="wap-ref">
		<input type="text" readonly value="<?php echo esc_attr( $refUrl ); ?>" onclick="this.select();" dir="ltr" />
		<code class="wap-code"><?php echo esc_html( $affiliate['code'] ); ?></code>
	</div>
	<p class="wap-muted wap-small">
		هر خریدار که از این لینک وارد شود تا ۳۰ روز به شما نسبت داده می‌شود، حتی اگر همان روز خرید نکند.
	</p>

	<h3>درخواست تسویه</h3>
	<?php if ( $balance->available()->compare( $minPayout ) >= 0 ) : ?>
		<form method="post" class="wap-form wap-form--inline">
			<?php wp_nonce_field( 'wap_payout', 'wap_payout_nonce' ); ?>
			<input type="hidden" name="wap_action" value="payout" />
			<label for="wap_amount">مبلغ (<?php echo esc_html( $currency ); ?>)</label>
			<input type="text" id="wap_amount" name="wap_amount" dir="ltr"
				value="<?php echo esc_attr( (string) $balance->available()->amount() ); ?>" />
			<button type="submit" class="button alt">ثبت درخواست</button>
		</form>
		<p class="wap-muted wap-small">حداقل مبلغ تسویه: <?php echo esc_html( number_format( $minPayout->toFloat() ) ); ?> <?php echo esc_html( $currency ); ?></p>
	<?php else : ?>
		<p class="wap-muted">
			حداقل مبلغ تسویه <?php echo esc_html( number_format( $minPayout->toFloat() ) ); ?> <?php echo esc_html( $currency ); ?> است.
			وقتی موجودی شما به این مقدار رسید، فرم درخواست فعال می‌شود.
		</p>
	<?php endif; ?>

	<h3>آخرین پورسانت‌ها</h3>
	<table class="wap-table">
		<thead>
			<tr>
				<th>سفارش</th>
				<th>مبلغ</th>
				<th>وضعیت</th>
				<th>تاریخ</th>
			</tr>
		</thead>
		<tbody>
		<?php if ( empty( $commissions ) ) : ?>
			<tr><td colspan="4" class="wap-muted">هنوز پورسانتی ثبت نشده است.</td></tr>
		<?php else : ?>
			<?php foreach ( $commissions as $row ) : ?>
				<tr>
					<td>#<?php echo esc_html( (string) $row['order_id'] ); ?></td>
					<td><?php echo esc_html( number_format( ((int) $row['amount']) ) ); ?> <?php echo esc_html( $currency ); ?></td>
					<td><span class="wap-badge wap-badge--<?php echo esc_attr( (string) $row['status'] ); ?>">
						<?php echo esc_html( $statusLabels[ (string) $row['status'] ] ?? (string) $row['status'] ); ?>
					</span></td>
					<td><?php echo esc_html( mysql2date( 'Y/m/d', (string) $row['created_at'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
		<?php endif; ?>
		</tbody>
	</table>

	<?php if ( ! empty( $payouts ) ) : ?>
		<h3>درخواست‌های تسویه</h3>
		<table class="wap-table">
			<thead>
				<tr><th>مبلغ</th><th>وضعیت</th><th>تاریخ درخواست</th></tr>
			</thead>
			<tbody>
			<?php foreach ( $payouts as $row ) : ?>
				<tr>
					<td><?php echo esc_html( number_format( (int) $row['amount'] ) ); ?> <?php echo esc_html( $currency ); ?></td>
					<td><?php echo esc_html( (string) $row['status'] ); ?></td>
					<td><?php echo esc_html( mysql2date( 'Y/m/d', (string) $row['requested_at'] ) ); ?></td>
				</tr>
			<?php endforeach; ?>
			</tbody>
		</table>
	<?php endif; ?>
</div>
