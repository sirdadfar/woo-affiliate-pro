<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration\Admin;

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Domain\Commission\Status;
use WooAffiliatePro\Domain\Money;
use WooAffiliatePro\Domain\Payout\PayoutStatus;
use WooAffiliatePro\Repository\AffiliateRepository;
use WooAffiliatePro\Repository\CommissionRepository;
use WooAffiliatePro\Repository\PayoutRepository;
use WooAffiliatePro\Support\PersianNumber;
use WooAffiliatePro\Support\Settings;

/**
 * منوی مدیریت و پردازش اکشن‌ها.
 *
 * همهٔ تغییرات از الگوی POST-Redirect-GET رد می‌شوند تا رفرش صفحه
 * باعث ثبت دوبارهٔ یک پرداخت نشود. هر اکشن غیرفقط‌خواندنی، هم
 * capability و هم nonce مخصوص خودش را بررسی می‌کند.
 */
final class AdminMenu {

	public const SLUG = 'woo-affiliate-pro';

	private const PER_PAGE = 20;

	public function register(): void {
		add_action( 'admin_menu', array( $this, 'menu' ) );
		add_action( 'admin_enqueue_scripts', array( $this, 'assets' ) );

		add_action( 'admin_post_wap_affiliate_status', array( $this, 'handleAffiliateStatus' ) );
		add_action( 'admin_post_wap_commission_status', array( $this, 'handleCommissionStatus' ) );
		add_action( 'admin_post_wap_payout_status', array( $this, 'handlePayoutStatus' ) );
		add_action( 'admin_post_wap_save_settings', array( $this, 'handleSaveSettings' ) );
	}

	public function assets( string $hook ): void {
		if ( false === strpos( $hook, self::SLUG ) ) {
			return;
		}

		wp_enqueue_style( 'wap-admin', WAP_URL . 'assets/css/admin.css', array(), WAP_VERSION );
	}

	public function menu(): void {
		add_menu_page(
			'همکاری در فروش',
			'همکاری در فروش',
			'manage_woocommerce',
			self::SLUG,
			array( $this, 'renderAffiliates' ),
			'dashicons-groups',
			56
		);

		add_submenu_page( self::SLUG, 'نمایندگان', 'نمایندگان', 'manage_woocommerce', self::SLUG, array( $this, 'renderAffiliates' ) );
		add_submenu_page( self::SLUG, 'پورسانت‌ها', 'پورسانت‌ها', 'manage_woocommerce', self::SLUG . '-commissions', array( $this, 'renderCommissions' ) );
		add_submenu_page( self::SLUG, 'تسویه‌ها', 'تسویه‌ها', 'manage_woocommerce', self::SLUG . '-payouts', array( $this, 'renderPayouts' ) );
		add_submenu_page( self::SLUG, 'تنظیمات', 'تنظیمات', 'manage_options', self::SLUG . '-settings', array( $this, 'renderSettings' ) );
	}

	public function renderAffiliates(): void {
		$this->guard( 'manage_woocommerce' );

		$repository = new AffiliateRepository();
		$status     = $this->statusFilter(
			array(
				AffiliateRepository::STATUS_PENDING,
				AffiliateRepository::STATUS_ACTIVE,
				AffiliateRepository::STATUS_REJECTED,
				AffiliateRepository::STATUS_DISABLED,
			)
		);
		$page       = $this->currentPage();
		$rows       = $repository->paginate( $page, self::PER_PAGE, $status );
		$total      = $repository->count();
		$filtered   = '' === $status ? $total : $repository->count( $status );
		$pending    = $repository->count( AffiliateRepository::STATUS_PENDING );
		$active     = $repository->count( AffiliateRepository::STATUS_ACTIVE );
		$disabled   = $repository->count( AffiliateRepository::STATUS_DISABLED );

		$filters = array(
			''                                    => 'همه',
			AffiliateRepository::STATUS_PENDING   => 'در انتظار تأیید',
			AffiliateRepository::STATUS_ACTIVE    => 'فعال',
			AffiliateRepository::STATUS_REJECTED  => 'رد شده',
			AffiliateRepository::STATUS_DISABLED  => 'غیرفعال',
		);
		?>
		<div class="wrap wap-admin" dir="rtl">
			<header class="wap-admin__head">
				<p class="wap-eyebrow">
					<?php $this->icon( 'users', 16 ); ?>
					همکاری در فروش
				</p>
				<h1>نمایندگان همکاری در فروش</h1>
				<p class="wap-admin__lede">وضعیت هر نماینده، نرخ پورسانت و کد معرف را از همین صفحه مدیریت کنید.</p>
			</header>
			<?php $this->flash(); ?>

			<nav class="wap-subnav" aria-label="فیلتر نمایندگان بر اساس وضعیت">
				<?php foreach ( $filters as $key => $label ) : ?>
					<a class="wap-pill<?php echo $status === $key ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $this->pageUrl( self::SLUG, '' === $key ? array() : array( 'status' => $key ) ) ); ?>"
						<?php if ( $status === $key ) : ?>aria-current="page"<?php endif; ?>>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="wap-stats">
				<article class="wap-stat">
					<span class="wap-stat__label"><?php $this->icon( 'users' ); ?>کل نمایندگان</span>
					<strong class="wap-stat__value"><?php echo esc_html( $this->count( $total ) ); ?></strong>
				</article>
				<article class="wap-stat wap-stat--warn">
					<span class="wap-stat__label"><?php $this->icon( 'clock' ); ?>در انتظار تأیید</span>
					<strong class="wap-stat__value"><?php echo esc_html( $this->count( $pending ) ); ?></strong>
				</article>
				<article class="wap-stat wap-stat--gold">
					<span class="wap-stat__label"><?php $this->icon( 'check-circle' ); ?>فعال</span>
					<strong class="wap-stat__value"><?php echo esc_html( $this->count( $active ) ); ?></strong>
				</article>
				<article class="wap-stat wap-stat--danger">
					<span class="wap-stat__label"><?php $this->icon( 'x-circle' ); ?>غیرفعال</span>
					<strong class="wap-stat__value"><?php echo esc_html( $this->count( $disabled ) ); ?></strong>
				</article>
			</div>

			<p class="wap-admin__meta"><?php echo esc_html( $this->count( $filtered ) ); ?> نماینده در این فهرست.</p>

			<div class="wap-table-wrap">
				<table class="wp-list-table widefat fixed wap-table">
					<caption class="wap-sr">فهرست نمایندگان همکاری در فروش</caption>
					<thead>
						<tr>
							<th scope="col" class="wap-col-id">#</th>
							<th scope="col">نام</th>
							<th scope="col">کد</th>
							<th scope="col">ایمیل</th>
							<th scope="col">موبایل</th>
							<th scope="col">نرخ</th>
							<th scope="col">وضعیت</th>
							<th scope="col">عملیات</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr>
							<td colspan="8">
								<div class="wap-empty">
									<?php $this->icon( 'inbox', 24 ); ?>
									<p class="wap-empty__title">نماینده‌ای ثبت نشده است.</p>
									<p class="wap-empty__hint">صفحهٔ ثبت‌نام را با شورت‌کد <code>[wap_join]</code> بسازید و لینکش را منتشر کنید.</p>
								</div>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<tr>
								<td data-label="#" class="wap-col-id"><?php echo (int) $row['id']; ?></td>
								<td data-label="نام"><?php echo esc_html( (string) $row['name'] ); ?></td>
								<td data-label="کد"><code><?php echo esc_html( (string) $row['code'] ); ?></code></td>
								<td data-label="ایمیل" class="wap-ltr"><?php echo esc_html( (string) $row['email'] ); ?></td>
								<td data-label="موبایل" class="wap-ltr" dir="ltr"><?php echo esc_html( (string) $row['mobile'] ); ?></td>
								<td data-label="نرخ" class="wap-num"><?php echo esc_html( $this->percent( (int) $row['rate_basis_points'] ) ); ?></td>
								<td data-label="وضعیت"><span class="wap-badge wap-badge--<?php echo esc_attr( (string) $row['status'] ); ?>"><?php echo esc_html( $this->affiliateStatusLabel( (string) $row['status'] ) ); ?></span></td>
								<td data-label="عملیات"><div class="wap-row-actions"><?php $this->affiliateActions( (int) $row['id'], (string) $row['status'] ); ?></div></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php $this->pager( $page, $filtered, self::SLUG, '' === $status ? array() : array( 'status' => $status ) ); ?>
		</div>
		<?php
	}

	public function renderCommissions(): void {
		$this->guard( 'manage_woocommerce' );

		$repository = new CommissionRepository();
		$status     = $this->statusFilter( Status::all() );
		$page       = $this->currentPage();
		$rows       = $repository->paginate( $page, self::PER_PAGE, $status );
		$filtered   = '' === $status ? $repository->count() : $repository->count( $status );
		$stats      = $repository->stats();
		$currency   = $this->currency();
		$decimals   = $this->priceDecimals();

		$filters = array( '' => 'همه' );
		foreach ( Status::all() as $state ) {
			$filters[ $state ] = Status::label( $state );
		}

		$affiliates = ( new AffiliateRepository() )->findByIds( array_map( 'intval', wp_list_pluck( $rows, 'affiliate_id' ) ) );
		?>
		<div class="wrap wap-admin" dir="rtl">
			<header class="wap-admin__head">
				<p class="wap-eyebrow">
					<?php $this->icon( 'coins', 16 ); ?>
					همکاری در فروش
				</p>
				<h1>پورسانت‌ها</h1>
				<p class="wap-admin__lede">هر پورسانت با سفارش و نماینده‌اش گره خورده است؛ برای تأیید یا پرداخت از ستون عملیات استفاده کنید.</p>
			</header>
			<?php $this->flash(); ?>

			<nav class="wap-subnav" aria-label="فیلتر پورسانت‌ها بر اساس وضعیت">
				<?php foreach ( $filters as $key => $label ) : ?>
					<a class="wap-pill<?php echo $status === $key ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $this->pageUrl( self::SLUG . '-commissions', '' === $key ? array() : array( 'status' => $key ) ) ); ?>"
						<?php if ( $status === $key ) : ?>aria-current="page"<?php endif; ?>>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="wap-stats">
				<article class="wap-stat wap-stat--warn">
					<span class="wap-stat__label"><?php $this->icon( 'clock' ); ?>در انتظار</span>
					<strong class="wap-stat__value"><?php echo esc_html( $this->money( (int) ( $stats[ Status::PENDING ]['amount'] ?? 0 ), $currency, $decimals ) ); ?></strong>
					<span class="wap-stat__foot"><?php echo esc_html( $this->count( (int) ( $stats[ Status::PENDING ]['count'] ?? 0 ) ) ); ?> مورد</span>
				</article>
				<article class="wap-stat wap-stat--gold">
					<span class="wap-stat__label"><?php $this->icon( 'check-circle' ); ?>تأییدشده</span>
					<strong class="wap-stat__value"><?php echo esc_html( $this->money( (int) ( $stats[ Status::APPROVED ]['amount'] ?? 0 ), $currency, $decimals ) ); ?></strong>
					<span class="wap-stat__foot"><?php echo esc_html( $this->count( (int) ( $stats[ Status::APPROVED ]['count'] ?? 0 ) ) ); ?> مورد</span>
				</article>
				<article class="wap-stat">
					<span class="wap-stat__label"><?php $this->icon( 'bank' ); ?>پرداخت‌شده</span>
					<strong class="wap-stat__value"><?php echo esc_html( $this->money( (int) ( $stats[ Status::PAID ]['amount'] ?? 0 ), $currency, $decimals ) ); ?></strong>
					<span class="wap-stat__foot"><?php echo esc_html( $this->count( (int) ( $stats[ Status::PAID ]['count'] ?? 0 ) ) ); ?> مورد</span>
				</article>
			</div>

			<p class="wap-admin__meta"><?php echo esc_html( $this->count( $filtered ) ); ?> مورد در این فهرست.</p>

			<div class="wap-table-wrap">
				<table class="wp-list-table widefat fixed wap-table">
					<caption class="wap-sr">فهرست پورسانت‌ها</caption>
					<thead>
						<tr>
							<th scope="col" class="wap-col-id">#</th>
							<th scope="col">نماینده</th>
							<th scope="col">سفارش</th>
							<th scope="col">مبلغ</th>
							<th scope="col">وضعیت</th>
							<th scope="col">تاریخ</th>
							<th scope="col">عملیات</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr>
							<td colspan="7">
								<div class="wap-empty">
									<?php $this->icon( 'inbox', 24 ); ?>
									<p class="wap-empty__title">پورسانتی ثبت نشده است.</p>
									<p class="wap-empty__hint">با اولین خرید از طریق لینک معرف، پورسانت در همین جدول ساخته می‌شود.</p>
								</div>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$affiliateId = (int) $row['affiliate_id'];
							$name        = isset( $affiliates[ $affiliateId ]['name'] ) ? (string) $affiliates[ $affiliateId ]['name'] : '';
							$rowCurrency = (string) ( $row['currency'] ?? $currency );
							?>
							<tr>
								<td data-label="#" class="wap-col-id"><?php echo (int) $row['id']; ?></td>
								<td data-label="نماینده">
									<?php if ( '' !== $name ) : ?>
										<?php echo esc_html( $name ); ?>
										<span class="wap-admin__sub">#<?php echo (int) $affiliateId; ?></span>
									<?php else : ?>
										#<?php echo (int) $affiliateId; ?>
									<?php endif; ?>
								</td>
								<td data-label="سفارش">
									<a href="<?php echo esc_url( $this->orderUrl( (int) $row['order_id'] ) ); ?>">
										#<?php echo esc_html( PersianNumber::digits( (string) $row['order_id'] ) ); ?>
									</a>
								</td>
								<td data-label="مبلغ" class="wap-num"><?php echo esc_html( $this->money( (int) $row['amount'], $rowCurrency, $decimals ) ); ?></td>
								<td data-label="وضعیت"><span class="wap-badge wap-badge--<?php echo esc_attr( (string) $row['status'] ); ?>"><?php echo esc_html( Status::label( (string) $row['status'] ) ); ?></span></td>
								<td data-label="تاریخ" class="wap-num"><?php echo esc_html( PersianNumber::digits( mysql2date( 'Y/m/d', (string) $row['created_at'] ) ) ); ?></td>
								<td data-label="عملیات"><div class="wap-row-actions"><?php $this->commissionActions( (int) $row['id'], (string) $row['status'] ); ?></div></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php $this->pager( $page, $filtered, self::SLUG . '-commissions', '' === $status ? array() : array( 'status' => $status ) ); ?>
		</div>
		<?php
	}

	public function renderPayouts(): void {
		$this->guard( 'manage_woocommerce' );

		$repository = new PayoutRepository();
		$status     = $this->statusFilter(
			array( PayoutStatus::REQUESTED, PayoutStatus::PROCESSING, PayoutStatus::PAID, PayoutStatus::REJECTED )
		);
		$page       = $this->currentPage();
		$rows       = $repository->paginate( $page, self::PER_PAGE, $status );
		$filtered   = '' === $status ? $repository->count() : $repository->count( $status );
		$stats      = $repository->stats();
		$currency   = $this->currency();
		$decimals   = $this->priceDecimals();

		$filters = array( '' => 'همه' );
		foreach ( array( PayoutStatus::REQUESTED, PayoutStatus::PROCESSING, PayoutStatus::PAID, PayoutStatus::REJECTED ) as $state ) {
			$filters[ $state ] = PayoutStatus::label( $state );
		}

		$affiliates = ( new AffiliateRepository() )->findByIds( array_map( 'intval', wp_list_pluck( $rows, 'affiliate_id' ) ) );
		?>
		<div class="wrap wap-admin" dir="rtl">
			<header class="wap-admin__head">
				<p class="wap-eyebrow">
					<?php $this->icon( 'bank', 16 ); ?>
					همکاری در فروش
				</p>
				<h1>درخواست‌های تسویه</h1>
				<p class="wap-admin__lede">پیش از پرداخت، مقصد واریز را با اطلاعات نماینده تطبیق دهید و شمارهٔ پیگیری را ثبت کنید.</p>
			</header>
			<?php $this->flash(); ?>

			<nav class="wap-subnav" aria-label="فیلتر تسویه‌ها بر اساس وضعیت">
				<?php foreach ( $filters as $key => $label ) : ?>
					<a class="wap-pill<?php echo $status === $key ? ' is-active' : ''; ?>"
						href="<?php echo esc_url( $this->pageUrl( self::SLUG . '-payouts', '' === $key ? array() : array( 'status' => $key ) ) ); ?>"
						<?php if ( $status === $key ) : ?>aria-current="page"<?php endif; ?>>
						<?php echo esc_html( $label ); ?>
					</a>
				<?php endforeach; ?>
			</nav>

			<div class="wap-stats">
				<article class="wap-stat wap-stat--warn">
					<span class="wap-stat__label"><?php $this->icon( 'clock' ); ?>در انتظار بررسی</span>
					<strong class="wap-stat__value"><?php echo esc_html( $this->money( (int) ( $stats[ PayoutStatus::REQUESTED ]['amount'] ?? 0 ), $currency, $decimals ) ); ?></strong>
					<span class="wap-stat__foot"><?php echo esc_html( $this->count( (int) ( $stats[ PayoutStatus::REQUESTED ]['count'] ?? 0 ) ) ); ?> مورد</span>
				</article>
				<article class="wap-stat">
					<span class="wap-stat__label"><?php $this->icon( 'coins' ); ?>در حال پردازش</span>
					<strong class="wap-stat__value"><?php echo esc_html( $this->money( (int) ( $stats[ PayoutStatus::PROCESSING ]['amount'] ?? 0 ), $currency, $decimals ) ); ?></strong>
					<span class="wap-stat__foot"><?php echo esc_html( $this->count( (int) ( $stats[ PayoutStatus::PROCESSING ]['count'] ?? 0 ) ) ); ?> مورد</span>
				</article>
				<article class="wap-stat wap-stat--gold">
					<span class="wap-stat__label"><?php $this->icon( 'check-circle' ); ?>پرداخت‌شده</span>
					<strong class="wap-stat__value"><?php echo esc_html( $this->money( (int) ( $stats[ PayoutStatus::PAID ]['amount'] ?? 0 ), $currency, $decimals ) ); ?></strong>
					<span class="wap-stat__foot"><?php echo esc_html( $this->count( (int) ( $stats[ PayoutStatus::PAID ]['count'] ?? 0 ) ) ); ?> مورد</span>
				</article>
			</div>

			<p class="wap-admin__meta"><?php echo esc_html( $this->count( $filtered ) ); ?> مورد در این فهرست.</p>

			<div class="wap-table-wrap">
				<table class="wp-list-table widefat fixed wap-table">
					<caption class="wap-sr">فهرست درخواست‌های تسویه</caption>
					<thead>
						<tr>
							<th scope="col" class="wap-col-id">#</th>
							<th scope="col">نماینده</th>
							<th scope="col">مبلغ</th>
							<th scope="col">مقصد واریز</th>
							<th scope="col">وضعیت</th>
							<th scope="col">تاریخ</th>
							<th scope="col">عملیات</th>
						</tr>
					</thead>
					<tbody>
					<?php if ( empty( $rows ) ) : ?>
						<tr>
							<td colspan="7">
								<div class="wap-empty">
									<?php $this->icon( 'inbox', 24 ); ?>
									<p class="wap-empty__title">درخواستی ثبت نشده است.</p>
									<p class="wap-empty__hint">نمایندگان از پنل خودشان درخواست تسویه ثبت می‌کنند.</p>
								</div>
							</td>
						</tr>
					<?php else : ?>
						<?php foreach ( $rows as $row ) : ?>
							<?php
							$affiliateId = (int) $row['affiliate_id'];
							$affiliate   = $affiliates[ $affiliateId ] ?? array();
							$name        = isset( $affiliate['name'] ) ? (string) $affiliate['name'] : '';
							$details     = isset( $affiliate['payout_details'] ) ? trim( (string) $affiliate['payout_details'] ) : '';
							$method      = isset( $affiliate['payout_method'] ) ? (string) $affiliate['payout_method'] : (string) $row['method'];
							?>
							<tr>
								<td data-label="#" class="wap-col-id"><?php echo (int) $row['id']; ?></td>
								<td data-label="نماینده">
									<?php if ( '' !== $name ) : ?>
										<?php echo esc_html( $name ); ?>
										<span class="wap-admin__sub">#<?php echo (int) $affiliateId; ?></span>
									<?php else : ?>
										#<?php echo (int) $affiliateId; ?>
									<?php endif; ?>
								</td>
								<td data-label="مبلغ" class="wap-num"><?php echo esc_html( $this->money( (int) $row['amount'], $currency, $decimals ) ); ?></td>
								<td data-label="مقصد واریز">
									<span class="wap-dest"><?php echo esc_html( $this->methodLabel( $method ) ); ?></span>
									<?php if ( '' !== $details ) : ?>
										<code class="wap-dest__value" dir="ltr"><?php echo esc_html( $details ); ?></code>
									<?php else : ?>
										<span class="wap-dest__missing">اطلاعات واریز ثبت نشده است.</span>
									<?php endif; ?>
								</td>
								<td data-label="وضعیت"><span class="wap-badge wap-badge--<?php echo esc_attr( (string) $row['status'] ); ?>"><?php echo esc_html( PayoutStatus::label( (string) $row['status'] ) ); ?></span></td>
								<td data-label="تاریخ" class="wap-num"><?php echo esc_html( PersianNumber::digits( mysql2date( 'Y/m/d', (string) $row['requested_at'] ) ) ); ?></td>
								<td data-label="عملیات"><div class="wap-row-actions"><?php $this->payoutActions( (int) $row['id'], (string) $row['status'] ); ?></div></td>
							</tr>
						<?php endforeach; ?>
					<?php endif; ?>
					</tbody>
				</table>
			</div>

			<?php $this->pager( $page, $filtered, self::SLUG . '-payouts', '' === $status ? array() : array( 'status' => $status ) ); ?>
		</div>
		<?php
	}

	public function renderSettings(): void {
		$this->guard( 'manage_options' );

		$settings      = Settings::all();
		$currency      = $this->currency();
		$decimals      = $this->priceDecimals();
		$categoryRates = '';

		if ( ! empty( $settings['category_rates'] ) && is_array( $settings['category_rates'] ) ) {
			foreach ( $settings['category_rates'] as $categoryId => $basisPoints ) {
				$categoryRates .= $categoryId . ':' . $this->number( (int) $basisPoints / 100, 2 ) . "\n";
			}
		}

		$step = $decimals > 0 ? '0.' . str_repeat( '0', max( 0, $decimals - 1 ) ) . '1' : '1';
		?>
		<div class="wrap wap-admin" dir="rtl">
			<header class="wap-admin__head">
				<p class="wap-eyebrow">
					<?php $this->icon( 'sliders', 16 ); ?>
					همکاری در فروش
				</p>
				<h1>تنظیمات همکاری در فروش</h1>
				<p class="wap-admin__lede">نرخ‌ها، استثناها و قواعد تسویه از اینجا اعمال می‌شود؛ تغییرات فقط پس از ذخیره اثر می‌گذارد.</p>
			</header>
			<?php $this->flash(); ?>

			<div class="wap-admin__panel">
				<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
					<?php wp_nonce_field( 'wap_save_settings' ); ?>
					<input type="hidden" name="action" value="wap_save_settings" />

					<table class="form-table">
						<tr class="wap-section-row">
							<td colspan="2"><h2 class="wap-form__section">عمومی</h2></td>
						</tr>
						<tr>
							<th><label for="cookie_days">مدت اعتبار لینک معرف (روز)</label></th>
							<td><input type="number" min="1" max="365" id="cookie_days" name="cookie_days"
								value="<?php echo esc_attr( (string) $settings['cookie_days'] ); ?>" />
								<p class="description">کوکی معرف تا این تعداد روز روی مرورگر خریدار می‌ماند.</p></td>
						</tr>
						<tr>
							<th><label for="default_rate">نرخ پیش‌فرض پورسانت (درصد)</label></th>
							<td><input type="number" step="0.01" min="0" max="100" id="default_rate" name="default_rate"
								value="<?php echo esc_attr( $this->number( (int) $settings['default_rate_bp'] / 100, 2 ) ); ?>" />
								<p class="description">نرخ دسته‌بندی، وقتی تعریف شده باشد، بر این نرخ اولویت دارد.</p></td>
						</tr>

						<tr class="wap-section-row">
							<td colspan="2"><h2 class="wap-form__section">مبنای محاسبه</h2></td>
						</tr>
						<tr>
							<th>مبنا</th>
							<td>
								<label><input type="checkbox" name="include_shipping" value="1" <?php checked( (bool) $settings['include_shipping'] ); ?> /> هزینهٔ ارسال هم پورسانت دارد</label><br />
								<label><input type="checkbox" name="include_tax" value="1" <?php checked( (bool) $settings['include_tax'] ); ?> /> مالیات هم در محاسبه لحاظ شود</label>
							</td>
						</tr>

						<tr class="wap-section-row">
							<td colspan="2"><h2 class="wap-form__section">نرخ‌ها و استثناها</h2></td>
						</tr>
						<tr>
							<th><label for="category_rates">نرخ اختصاصی دسته‌بندی</label></th>
							<td>
								<textarea id="category_rates" name="category_rates" rows="4" dir="ltr"
									placeholder="12:15&#10;18:5"><?php echo esc_textarea( trim( $categoryRates ) ); ?></textarea>
								<p class="description">هر خط: شناسهٔ دسته:درصد — مثلاً <code>12:15</code></p>
							</td>
						</tr>
						<tr>
							<th><label for="exclude_products">محصولات مستثنا</label></th>
							<td><input type="text" class="regular-text" id="exclude_products" name="exclude_products" dir="ltr"
								value="<?php echo esc_attr( (string) $settings['exclude_products'] ); ?>" placeholder="101,205" />
								<p class="description">شناسهٔ محصول‌ها با کاما؛ از این محصول‌ها پورسانت کسر نمی‌شود.</p></td>
						</tr>
						<tr>
							<th><label for="exclude_categories">دسته‌های مستثنا</label></th>
							<td><input type="text" class="regular-text" id="exclude_categories" name="exclude_categories" dir="ltr"
								value="<?php echo esc_attr( (string) $settings['exclude_categories'] ); ?>" placeholder="33" />
								<p class="description">شناسهٔ دسته‌ها با کاما.</p></td>
						</tr>
						<tr>
							<th><label for="max_commission">سقف پورسانت هر سفارش (<?php echo esc_html( $currency ); ?>)</label></th>
							<td><input type="number" min="0" step="<?php echo esc_attr( $step ); ?>" id="max_commission" name="max_commission"
								value="<?php echo esc_attr( $this->number( (int) $settings['max_commission'] / ( 10 ** $decimals ), $decimals ) ); ?>" />
								<p class="description">صفر = بدون سقف</p></td>
						</tr>

						<tr class="wap-section-row">
							<td colspan="2"><h2 class="wap-form__section">تسویه</h2></td>
						</tr>
						<tr>
							<th><label for="min_payout">حداقل مبلغ تسویه (<?php echo esc_html( $currency ); ?>)</label></th>
							<td><input type="number" min="0" step="<?php echo esc_attr( $step ); ?>" id="min_payout" name="min_payout"
								value="<?php echo esc_attr( $this->number( (int) $settings['min_payout'] / ( 10 ** $decimals ), $decimals ) ); ?>" />
								<p class="description">تا وقتی موجودی نماینده به این مبلغ نرسد، فرم درخواست تسویه برای او فعال نمی‌شود.</p></td>
						</tr>

						<tr class="wap-section-row">
							<td colspan="2"><h2 class="wap-form__section">چرخهٔ پورسانت و ثبت‌نام</h2></td>
						</tr>
						<tr>
							<th><label for="approve_on">تأیید پورسانت در وضعیت</label></th>
							<td>
								<select id="approve_on" name="approve_on">
									<option value="completed" <?php selected( $settings['approve_on'], 'completed' ); ?>>تکمیل سفارش</option>
									<option value="processing" <?php selected( $settings['approve_on'], 'processing' ); ?>>در حال انجام</option>
								</select>
							</td>
						</tr>
						<tr>
							<th>ثبت‌نام</th>
							<td><label><input type="checkbox" name="auto_approve" value="1" <?php checked( (bool) $settings['auto_approve'] ); ?> /> نمایندهٔ جدید بدون بررسی تأیید شود</label></td>
						</tr>

						<tr class="wap-section-row">
							<td colspan="2"><h2 class="wap-form__section">حذف افزونه</h2></td>
						</tr>
						<tr>
							<th>داده‌های مالی</th>
							<td>
								<label><input type="checkbox" name="delete_data_on_uninstall" value="1" <?php checked( (bool) $settings['delete_data_on_uninstall'] ); ?> /> هنگام حذف افزونه، جداول و تنظیمات هم پاک شوند</label>
								<p class="description">پیش‌فرض خاموش است. با فعال کردن این گزینه، سابقهٔ پورسانت و تسویه برای همیشه از بین می‌رود.</p>
							</td>
						</tr>
					</table>

					<div class="wap-savebar">
						<?php submit_button( 'ذخیره تنظیمات', 'primary', 'submit', false ); ?>
						<p class="wap-savebar__note">تغییرات پس از ذخیره روی محاسبهٔ پورسانت‌های بعدی اثر می‌گذارد.</p>
					</div>
				</form>
			</div>
		</div>
		<?php
	}

	/* ------------------------------------------------------------ actions -- */

	public function handleAffiliateStatus(): void {
		$this->guard( 'manage_woocommerce', 'wap_action' );

		$id     = (int) $this->posted( 'id' );
		$status = sanitize_key( $this->posted( 'status' ) );

		$allowed = array(
			AffiliateRepository::STATUS_ACTIVE,
			AffiliateRepository::STATUS_PENDING,
			AffiliateRepository::STATUS_REJECTED,
			AffiliateRepository::STATUS_DISABLED,
		);

		if ( $id > 0 && in_array( $status, $allowed, true ) ) {
			$updated = ( new AffiliateRepository() )->update( $id, array( 'status' => $status ) );

			$this->redirect(
				array(
					'wap_msg' => $updated ? 'affiliate_updated' : 'invalid',
				)
			);
		}

		$this->redirect( array( 'wap_msg' => 'invalid' ) );
	}

	public function handleCommissionStatus(): void {
		$this->guard( 'manage_woocommerce', 'wap_action' );

		$id     = (int) $this->posted( 'id' );
		$status = sanitize_key( $this->posted( 'status' ) );

		if ( $id > 0 && in_array( $status, Status::all(), true ) ) {
			$repository = new CommissionRepository();
			$row        = $repository->find( $id );

			if ( null !== $row ) {
				try {
					$allowed = Status::canTransition( (string) $row['status'], $status );
				} catch ( \InvalidArgumentException $e ) {
					$allowed = false;
				}

				if ( $allowed ) {
					$updated = $repository->updateStatus( $id, $status, 'تغییر دستی توسط مدیر' );

					$this->redirect(
						array(
							'page'    => self::SLUG . '-commissions',
							'wap_msg' => $updated ? 'commission_updated' : 'invalid',
						)
					);
				}
			}
		}

		$this->redirect( array( 'page' => self::SLUG . '-commissions', 'wap_msg' => 'invalid' ) );
	}

	public function handlePayoutStatus(): void {
		$this->guard( 'manage_woocommerce', 'wap_action' );

		$id        = (int) $this->posted( 'id' );
		$status    = sanitize_key( $this->posted( 'status' ) );
		$reference = sanitize_text_field( $this->posted( 'reference' ) );

		if ( $id > 0 && in_array( $status, array( PayoutStatus::PROCESSING, PayoutStatus::PAID, PayoutStatus::REJECTED ), true ) ) {
			$repository = new PayoutRepository();
			$row        = $repository->find( $id );

			if ( null !== $row ) {
				try {
					$allowed = PayoutStatus::canTransition( (string) $row['status'], $status );
				} catch ( \InvalidArgumentException $e ) {
					$allowed = false;
				}

				if ( $allowed ) {
					$updated = $repository->updateStatus( $id, $status, $reference, 'تغییر وضعیت توسط مدیر' );

					$this->redirect(
						array(
							'page'    => self::SLUG . '-payouts',
							'wap_msg' => $updated ? 'payout_updated' : 'invalid',
						)
					);
				}
			}
		}

		$this->redirect( array( 'page' => self::SLUG . '-payouts', 'wap_msg' => 'invalid' ) );
	}

	public function handleSaveSettings(): void {
		$this->guard( 'manage_options', 'wap_save_settings' );

		$current   = Settings::all();
		$currency  = $this->currency();
		$decimals  = $this->priceDecimals();
		$rawRate   = $this->posted( 'default_rate' );
		$rateValue = is_numeric( $rawRate ) ? (float) $rawRate : ( (int) $current['default_rate_bp'] / 100 );
		$rateBp    = (int) round( max( 0, min( 100, $rateValue ) ) * 100 );

		$categoryRates = array();
		$rawRates      = sanitize_textarea_field( $this->posted( 'category_rates' ) );

		$lines = preg_split( '/\R/', $rawRates ) ?: array();
		$lines = array_slice( $lines, 0, 500 );

		foreach ( $lines as $line ) {
			$line = trim( $line );

			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}

			list( $categoryId, $percent ) = array_map( 'trim', explode( ':', $line, 2 ) );

			$categoryId = (int) $this->latinDigits( $categoryId );
			$percent    = $this->latinDigits( $percent );

			if ( $categoryId > 0 && is_numeric( $percent ) ) {
				$categoryRates[ $categoryId ] = (int) round( max( 0, min( 100, (float) $percent ) ) * 100 );
			}
		}

		Settings::update(
			array(
				'cookie_days'              => max( 1, min( 365, '' !== $this->posted( 'cookie_days' ) ? (int) $this->posted( 'cookie_days' ) : (int) $current['cookie_days'] ) ),
				'default_rate_bp'          => $rateBp,
				'include_shipping'         => ! empty( $_POST['include_shipping'] ),
				'include_tax'              => ! empty( $_POST['include_tax'] ),
				'category_rates'           => $categoryRates,
				'exclude_products'         => $this->normalizeIdList( sanitize_text_field( $this->posted( 'exclude_products' ) ) ),
				'exclude_categories'       => $this->normalizeIdList( sanitize_text_field( $this->posted( 'exclude_categories' ) ) ),
				'max_commission'           => $this->postedMoney( 'max_commission', (int) $current['max_commission'], $currency, $decimals ),
				'min_payout'               => $this->postedMoney( 'min_payout', (int) $current['min_payout'], $currency, $decimals ),
				'approve_on'               => 'processing' === $this->posted( 'approve_on' ) ? 'processing' : 'completed',
				'auto_approve'             => ! empty( $_POST['auto_approve'] ),
				'delete_data_on_uninstall' => ! empty( $_POST['delete_data_on_uninstall'] ),
			)
		);

		$this->redirect( array( 'page' => self::SLUG . '-settings', 'wap_msg' => 'settings_saved' ) );
	}

	/* ------------------------------------------------------------ helpers -- */

	private function guard( string $capability, string $nonceAction = '' ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die(
				esc_html( 'دسترسی مجاز نیست.' ),
				'',
				array( 'response' => 403 )
			);
		}

		if ( '' !== $nonceAction ) {
			check_admin_referer( $nonceAction );
		}
	}

	/**
	 * @param string[] $allowed
	 */
	private function statusFilter( array $allowed ): string {
		$status = sanitize_key( $this->query( 'status' ) );

		return in_array( $status, $allowed, true ) ? $status : '';
	}

	private function currentPage(): int {
		$paged = $this->query( 'paged' );

		return '' !== $paged ? max( 1, (int) $paged ) : 1;
	}

	/**
	 * خواندن یک مقدار اسکالر از POST.
	 *
	 * بدون این گارد، ارسال `status[]=x` باعث TypeError در sanitize_key
	 * می‌شد و صفحهٔ مدیریت با خطای ۵۰۰ می‌افتاد. هرگز به آرایه اعتماد نمی‌کنیم.
	 */
	private function posted( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return '';
		}

		return (string) wp_unslash( $_POST[ $key ] );
	}

	private function query( string $key ): string {
		if ( ! isset( $_GET[ $key ] ) || ! is_scalar( $_GET[ $key ] ) ) {
			return '';
		}

		return (string) wp_unslash( $_GET[ $key ] );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function url( array $args = array() ): string {
		return $this->pageUrl( self::SLUG, $args );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function pageUrl( string $page, array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => $page ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function redirect( array $args ): void {
		$page = isset( $args['page'] ) ? (string) $args['page'] : self::SLUG;

		unset( $args['page'] );

		wp_safe_redirect( $this->pageUrl( $page, $args ) );
		exit;
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function pager( int $page, int $total, string $pageSlug, array $args = array() ): void {
		$pages = (int) ceil( $total / self::PER_PAGE );

		if ( $pages <= 1 ) {
			return;
		}

		$page = max( 1, min( $pages, $page ) );
		?>
		<nav class="wap-pager" aria-label="صفحه‌بندی">
			<?php if ( $page > 1 ) : ?>
				<a class="wap-pager__step" href="<?php echo esc_url( $this->pageUrl( $pageSlug, array_merge( $args, array( 'paged' => $page - 1 ) ) ) ); ?>" rel="prev" aria-label="صفحهٔ قبل">قبلی</a>
			<?php else : ?>
				<span class="wap-pager__step is-disabled" aria-disabled="true">قبلی</span>
			<?php endif; ?>

			<?php foreach ( $this->pageNumbers( $page, $pages ) as $number ) : ?>
				<?php if ( 0 === $number ) : ?>
					<span class="wap-pager__gap" aria-hidden="true">…</span>
				<?php elseif ( $number === $page ) : ?>
					<span class="wap-pager__current" aria-current="page"><?php echo esc_html( $this->count( $number ) ); ?></span>
				<?php else : ?>
					<a class="wap-pager__link" href="<?php echo esc_url( $this->pageUrl( $pageSlug, array_merge( $args, array( 'paged' => $number ) ) ) ); ?>"><?php echo esc_html( $this->count( $number ) ); ?></a>
				<?php endif; ?>
			<?php endforeach; ?>

			<?php if ( $page < $pages ) : ?>
				<a class="wap-pager__step" href="<?php echo esc_url( $this->pageUrl( $pageSlug, array_merge( $args, array( 'paged' => $page + 1 ) ) ) ); ?>" rel="next" aria-label="صفحهٔ بعد">بعدی</a>
			<?php else : ?>
				<span class="wap-pager__step is-disabled" aria-disabled="true">بعدی</span>
			<?php endif; ?>
		</nav>
		<?php
	}

	/**
	 * شماره‌های صفحه با حذف بازه‌های طولانی؛ صفر یعنی «…».
	 *
	 * @return int[]
	 */
	private function pageNumbers( int $page, int $pages ): array {
		if ( $pages <= 9 ) {
			return range( 1, $pages );
		}

		$numbers = array( 1 );
		$start   = max( 2, $page - 1 );
		$end     = min( $pages - 1, $page + 1 );

		if ( $start > 2 ) {
			$numbers[] = 0;
		}

		for ( $i = $start; $i <= $end; $i++ ) {
			$numbers[] = $i;
		}

		if ( $end < $pages - 1 ) {
			$numbers[] = 0;
		}

		$numbers[] = $pages;

		return $numbers;
	}

	private function flash(): void {
		$key = sanitize_key( $this->query( 'wap_msg' ) );

		if ( '' === $key ) {
			return;
		}

		$messages = array(
			'affiliate_updated'  => 'وضعیت نماینده ذخیره شد.',
			'commission_updated' => 'وضعیت پورسانت ذخیره شد.',
			'payout_updated'     => 'وضعیت تسویه ذخیره شد.',
			'settings_saved'     => 'تنظیمات ذخیره شد.',
			'invalid'            => 'درخواست نامعتبر بود.',
		);

		if ( ! isset( $messages[ $key ] ) ) {
			return;
		}

		$isError = 'invalid' === $key;

		printf(
			'<div class="notice notice-%s is-dismissible wap-flash" role="%s"><span class="wap-flash__icon" aria-hidden="true">%s</span><p>%s</p></div>',
			$isError ? 'error' : 'success',
			$isError ? 'alert' : 'status',
			$this->svg( $isError ? 'x-circle' : 'check-circle', 20 ),
			esc_html( $messages[ $key ] )
		);
	}

	private function affiliateStatusLabel( string $status ): string {
		$labels = array(
			AffiliateRepository::STATUS_ACTIVE   => 'فعال',
			AffiliateRepository::STATUS_PENDING  => 'در انتظار تأیید',
			AffiliateRepository::STATUS_REJECTED => 'رد شده',
			AffiliateRepository::STATUS_DISABLED => 'غیرفعال',
		);

		return $labels[ $status ] ?? $status;
	}

	private function affiliateActions( int $id, string $status ): void {
		$targets = array();

		if ( AffiliateRepository::STATUS_ACTIVE !== $status ) {
			$targets['فعال‌سازی'] = AffiliateRepository::STATUS_ACTIVE;
		}
		if ( AffiliateRepository::STATUS_DISABLED !== $status ) {
			$targets['غیرفعال‌سازی'] = AffiliateRepository::STATUS_DISABLED;
		}
		if ( AffiliateRepository::STATUS_REJECTED !== $status ) {
			$targets['رد'] = AffiliateRepository::STATUS_REJECTED;
		}

		$this->actionButtons( 'wap_affiliate_status', $id, $targets, 'نمایندهٔ #' . $id );
	}

	private function commissionActions( int $id, string $status ): void {
		$targets = array();

		foreach ( array( Status::APPROVED => 'تأیید', Status::PAID => 'پرداخت شد', Status::REJECTED => 'رد', Status::REVERSED => 'برگشت' ) as $target => $label ) {
			if ( $target === $status ) {
				continue;
			}

			try {
				if ( Status::canTransition( $status, $target ) ) {
					$targets[ $label ] = $target;
				}
			} catch ( \InvalidArgumentException $e ) {
				// وضعیت ناشناخته در ردیف قدیمی: هیچ اکشنی نمایش نده.
				return;
			}
		}

		$this->actionButtons( 'wap_commission_status', $id, $targets, 'پورسانت #' . $id );
	}

	private function payoutActions( int $id, string $status ): void {
		if ( PayoutStatus::PROCESSING === $status ) {
			$this->payoutForm( $id, PayoutStatus::PAID, 'پرداخت شد', 'success', true );
			$this->payoutForm( $id, PayoutStatus::REJECTED, 'رد', 'danger', false );

			return;
		}

		if ( PayoutStatus::REQUESTED === $status ) {
			$this->payoutForm( $id, PayoutStatus::PROCESSING, 'در حال پردازش', 'neutral', false );
			$this->payoutForm( $id, PayoutStatus::PAID, 'پرداخت شد', 'success', true );
			$this->payoutForm( $id, PayoutStatus::REJECTED, 'رد', 'danger', false );
		}
	}

	private function payoutForm( int $id, string $status, string $label, string $tone, bool $withReference ): void {
		?>
		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" class="wap-payout-form">
			<?php wp_nonce_field( 'wap_action' ); ?>
			<input type="hidden" name="action" value="wap_payout_status" />
			<input type="hidden" name="id" value="<?php echo (int) $id; ?>" />
			<input type="hidden" name="status" value="<?php echo esc_attr( $status ); ?>" />
			<?php if ( $withReference ) : ?>
				<label class="wap-sr" for="wap_reference_<?php echo (int) $id; ?>_<?php echo esc_attr( $status ); ?>">شمارهٔ پیگیری تسویه #<?php echo (int) $id; ?></label>
				<input class="wap-ref-input" type="text" id="wap_reference_<?php echo (int) $id; ?>_<?php echo esc_attr( $status ); ?>" name="reference" placeholder="شمارهٔ پیگیری" dir="ltr" autocomplete="off" />
			<?php endif; ?>
			<button type="submit" class="button button-small wap-mini wap-mini--<?php echo esc_attr( $tone ); ?>"
				aria-label="<?php echo esc_attr( $label . ' — تسویه #' . $id ); ?>"><?php echo esc_html( $label ); ?></button>
		</form>
		<?php
	}

	/**
	 * @param array<string, string> $targets
	 */
	private function actionButtons( string $action, int $id, array $targets, string $context = '' ): void {
		foreach ( $targets as $label => $target ) {
			$ariaLabel = '' === $context ? $label : $label . ' — ' . $context;
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wap_action' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
				<input type="hidden" name="id" value="<?php echo (int) $id; ?>" />
				<input type="hidden" name="status" value="<?php echo esc_attr( $target ); ?>" />
				<button type="submit" class="button button-small wap-mini wap-mini--<?php echo esc_attr( $this->actionTone( $target ) ); ?>"
					aria-label="<?php echo esc_attr( $ariaLabel ); ?>"><?php echo esc_html( $label ); ?></button>
			</form>
			<?php
		}
	}

	/**
	 * رنگی‌کردن دکمهٔ عملیات بر اساس مقصدِ تغییر وضعیت؛ هرگز از رنگ
	 * به‌تنهایی برای انتقال معنا استفاده نمی‌شود (متن دکمه همیشه هست).
	 */
	private function actionTone( string $target ): string {
		if ( in_array( $target, array( 'rejected', 'reversed', 'disabled' ), true ) ) {
			return 'danger';
		}

		if ( in_array( $target, array( 'paid', 'approved', 'active' ), true ) ) {
			return 'success';
		}

		return 'neutral';
	}

	private function methodLabel( string $method ): string {
		$labels = array(
			'card'   => 'شمارهٔ کارت',
			'bank'   => 'شمارهٔ شبا',
			'wallet' => 'کیف پول',
		);

		return $labels[ $method ] ?? $method;
	}

	private function currency(): string {
		return function_exists( 'get_woocommerce_currency' ) ? (string) get_woocommerce_currency() : 'IRR';
	}

	private function priceDecimals(): int {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 0;

		return max( 0, min( 4, $decimals ) );
	}

	private function money( int $amount, string $currency, int $decimals ): string {
		return PersianNumber::money( Money::of( $amount, $currency, $decimals )->format() ) . ' ' . $currency;
	}

	private function percent( int $basisPoints ): string {
		return $this->number( $basisPoints / 100, 2 ) . '٪';
	}

	private function number( float $value, int $decimals ): string {
		$formatted = number_format( $value, $decimals, '.', '' );

		if ( $decimals > 0 ) {
			$formatted = rtrim( rtrim( $formatted, '0' ), '.' );
		}

		return PersianNumber::digits( '' === $formatted ? '0' : $formatted );
	}

	/**
	 * عدد شمارشی با ارقام و جداکنندهٔ فارسی (پیشخوان هم فارسی است).
	 */
	private function count( int $value ): string {
		return PersianNumber::digits( number_format( $value ) );
	}

	private function postedMoney( string $key, int $fallback, string $currency, int $decimals ): int {
		if ( ! isset( $_POST[ $key ] ) ) {
			return $fallback;
		}

		$raw = sanitize_text_field( $this->posted( $key ) );

		try {
			$money = Money::fromString( $raw, $currency, $decimals );

			return max( 0, $money->amount() );
		} catch ( \InvalidArgumentException $e ) {
			return $fallback;
		}
	}

	private function normalizeIdList( string $raw ): string {
		$parts = preg_split( '/[\s,،]+/u', $raw ) ?: array();
		$ids   = array();

		foreach ( array_slice( $parts, 0, 500 ) as $part ) {
			$id = (int) $this->latinDigits( $part );

			if ( $id > 0 ) {
				$ids[ $id ] = $id;
			}
		}

		return implode( ',', array_values( $ids ) );
	}

	private function latinDigits( string $value ): string {
		return strtr(
			$value,
			array(
				'۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
				'۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
				'٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
				'٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
			)
		);
	}

	private function orderUrl( int $orderId ): string {
		if ( function_exists( 'wc_get_order' ) ) {
			$order = wc_get_order( $orderId );

			if ( $order && method_exists( $order, 'get_edit_order_url' ) ) {
				return (string) $order->get_edit_order_url();
			}
		}

		return admin_url( 'post.php?post=' . $orderId . '&action=edit' );
	}

	/**
	 * آیکون تزئینی خطی (Phosphor-style). آیکون‌ها همیشه aria-hidden هستند
	 * چون متن کنارشان معنا را می‌رساند و صفحه‌خوان دوباره نخواند.
	 */
	private function icon( string $name, int $size = 20 ): void {
		echo $this->svg( $name, $size ); // phpcs:ignore WordPress.Security.EscapeOutput.OutputNotEscaped -- هندل داخلی SVG.
	}

	private function svg( string $name, int $size = 20 ): string {
		$paths = array(
			'users'        => '<path d="M16 19v-1.5a3.5 3.5 0 0 0-3.5-3.5h-5A3.5 3.5 0 0 0 4 17.5V19"/><circle cx="10" cy="8" r="3.2"/><path d="M20 19v-1.4a3.4 3.4 0 0 0-2.6-3.3"/><path d="M15.5 5.2a3.2 3.2 0 0 1 0 5.9"/>',
			'coins'        => '<ellipse cx="12" cy="7" rx="6.5" ry="3"/><path d="M5.5 7v4c0 1.66 2.91 3 6.5 3s6.5-1.34 6.5-3V7"/><path d="M5.5 11v4c0 1.66 2.91 3 6.5 3s6.5-1.34 6.5-3v-4"/>',
			'bank'         => '<path d="M4 9.5h16"/><path d="M12 4l8 5.5H4z"/><path d="M6.5 9.5V17M12 9.5V17M17.5 9.5V17"/><path d="M4 19.5h16"/>',
			'sliders'      => '<path d="M4 8h9M17 8h3M4 16h3M11 16h9"/><circle cx="15" cy="8" r="2"/><circle cx="9" cy="16" r="2"/>',
			'clock'        => '<circle cx="12" cy="12" r="8.2"/><path d="M12 7.6V12l2.8 1.7"/>',
			'check-circle' => '<circle cx="12" cy="12" r="8.2"/><path d="M8.6 12.2l2.3 2.3 4.5-4.6"/>',
			'x-circle'     => '<circle cx="12" cy="12" r="8.2"/><path d="M9.2 9.2l5.6 5.6M14.8 9.2l-5.6 5.6"/>',
			'inbox'        => '<path d="M4.5 12.5l2.4-6.3A2 2 0 0 1 8.8 5h6.4a2 2 0 0 1 1.9 1.2l2.4 6.3"/><path d="M4.5 12.5h3.7l1 2.2h5.6l1-2.2h3.7v4.3a2 2 0 0 1-2 2h-11a2 2 0 0 1-2-2z"/>',
		);

		$shape = $paths[ $name ] ?? $paths['inbox'];

		return '<svg class="wap-ico" xmlns="http://www.w3.org/2000/svg" width="' . (int) $size . '" height="' . (int) $size
			. '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.6" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true" focusable="false">'
			. $shape . '</svg>';
	}
}
