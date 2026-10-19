<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration\Admin;

use WooAffiliatePro\Domain\Commission\Status;
use WooAffiliatePro\Domain\Payout\PayoutStatus;
use WooAffiliatePro\Repository\AffiliateRepository;
use WooAffiliatePro\Repository\CommissionRepository;
use WooAffiliatePro\Repository\PayoutRepository;
use WooAffiliatePro\Support\Settings;

/**
 * منوی مدیریت و پردازش اکشن‌ها.
 *
 * همهٔ تغییرات از الگوی POST-Redirect-GET رد می‌شوند تا رفرش صفحه
 * باعث ثبت دوبارهٔ یک پرداخت نشود.
 */
final class AdminMenu {

	public const SLUG = 'woo-affiliate-pro';

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
		$status     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$rows       = $repository->paginate( 1, 100, $status );
		?>
		<div class="wrap wap-admin" dir="rtl">
			<h1>نمایندگان همکاری در فروش</h1>
			<?php $this->flash(); ?>

			<p>
				<a class="button" href="<?php echo esc_url( $this->url() ); ?>">همه</a>
				<a class="button" href="<?php echo esc_url( $this->url( array( 'status' => 'pending' ) ) ); ?>">در انتظار تأیید</a>
				<a class="button" href="<?php echo esc_url( $this->url( array( 'status' => 'active' ) ) ); ?>">فعال</a>
				<a class="button" href="<?php echo esc_url( $this->url( array( 'status' => 'disabled' ) ) ); ?>">غیرفعال</a>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th>#</th><th>نام</th><th>کد</th><th>ایمیل</th><th>موبایل</th>
						<th>نرخ</th><th>وضعیت</th><th>عملیات</th>
					</tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="8">نماینده‌ای ثبت نشده است.</td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo (int) $row['id']; ?></td>
							<td><?php echo esc_html( (string) $row['name'] ); ?></td>
							<td><code><?php echo esc_html( (string) $row['code'] ); ?></code></td>
							<td><?php echo esc_html( (string) $row['email'] ); ?></td>
							<td dir="ltr"><?php echo esc_html( (string) $row['mobile'] ); ?></td>
							<td><?php echo esc_html( number_format( (int) $row['rate_basis_points'] / 100, 2 ) ); ?>٪</td>
							<td><?php echo esc_html( $this->affiliateStatusLabel( (string) $row['status'] ) ); ?></td>
							<td><?php $this->affiliateActions( (int) $row['id'], (string) $row['status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function renderCommissions(): void {
		$this->guard( 'manage_woocommerce' );

		$repository = new CommissionRepository();
		$status     = isset( $_GET['status'] ) ? sanitize_key( wp_unslash( $_GET['status'] ) ) : '';
		$rows       = $repository->paginate( 1, 100, $status );
		?>
		<div class="wrap wap-admin" dir="rtl">
			<h1>پورسانت‌ها</h1>
			<?php $this->flash(); ?>

			<p>
				<a class="button" href="<?php echo esc_url( $this->url( array( 'page' => self::SLUG . '-commissions' ) ) ); ?>">همه</a>
				<a class="button" href="<?php echo esc_url( $this->url( array( 'page' => self::SLUG . '-commissions', 'status' => 'pending' ) ) ); ?>">در انتظار</a>
				<a class="button" href="<?php echo esc_url( $this->url( array( 'page' => self::SLUG . '-commissions', 'status' => 'approved' ) ) ); ?>">تأییدشده</a>
			</p>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr><th>#</th><th>نماینده</th><th>سفارش</th><th>مبلغ</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7">پورسانتی ثبت نشده است.</td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo (int) $row['id']; ?></td>
							<td>#<?php echo (int) $row['affiliate_id']; ?></td>
							<td>
								<a href="<?php echo esc_url( admin_url( 'post.php?post=' . (int) $row['order_id'] . '&action=edit' ) ); ?>">
									#<?php echo (int) $row['order_id']; ?>
								</a>
							</td>
							<td><?php echo esc_html( number_format( (int) $row['amount'] ) ); ?> <?php echo esc_html( (string) $row['currency'] ); ?></td>
							<td><?php echo esc_html( Status::label( (string) $row['status'] ) ); ?></td>
							<td><?php echo esc_html( mysql2date( 'Y/m/d', (string) $row['created_at'] ) ); ?></td>
							<td><?php $this->commissionActions( (int) $row['id'], (string) $row['status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function renderPayouts(): void {
		$this->guard( 'manage_woocommerce' );

		$repository = new PayoutRepository();
		$rows       = $repository->paginate( 1, 100 );
		?>
		<div class="wrap wap-admin" dir="rtl">
			<h1>درخواست‌های تسویه</h1>
			<?php $this->flash(); ?>

			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr><th>#</th><th>نماینده</th><th>مبلغ</th><th>روش</th><th>وضعیت</th><th>تاریخ</th><th>عملیات</th></tr>
				</thead>
				<tbody>
				<?php if ( empty( $rows ) ) : ?>
					<tr><td colspan="7">درخواستی ثبت نشده است.</td></tr>
				<?php else : ?>
					<?php foreach ( $rows as $row ) : ?>
						<tr>
							<td><?php echo (int) $row['id']; ?></td>
							<td>#<?php echo (int) $row['affiliate_id']; ?></td>
							<td><?php echo esc_html( number_format( (int) $row['amount'] ) ); ?></td>
							<td><?php echo esc_html( (string) $row['method'] ); ?></td>
							<td><?php echo esc_html( PayoutStatus::label( (string) $row['status'] ) ); ?></td>
							<td><?php echo esc_html( mysql2date( 'Y/m/d', (string) $row['requested_at'] ) ); ?></td>
							<td><?php $this->payoutActions( (int) $row['id'], (string) $row['status'] ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public function renderSettings(): void {
		$this->guard( 'manage_options' );

		$settings = Settings::all();
		$categoryRates = '';
		if ( ! empty( $settings['category_rates'] ) && is_array( $settings['category_rates'] ) ) {
			foreach ( $settings['category_rates'] as $categoryId => $basisPoints ) {
				$categoryRates .= $categoryId . ':' . ( (int) $basisPoints / 100 ) . "\n";
			}
		}
		?>
		<div class="wrap wap-admin" dir="rtl">
			<h1>تنظیمات همکاری در فروش</h1>
			<?php $this->flash(); ?>

			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<?php wp_nonce_field( 'wap_save_settings' ); ?>
				<input type="hidden" name="action" value="wap_save_settings" />

				<table class="form-table">
					<tr>
						<th><label for="cookie_days">مدت اعتبار لینک معرف (روز)</label></th>
						<td><input type="number" min="1" max="365" id="cookie_days" name="cookie_days"
							value="<?php echo esc_attr( (string) $settings['cookie_days'] ); ?>" /></td>
					</tr>
					<tr>
						<th><label for="default_rate">نرخ پیش‌فرض پورسانت (درصد)</label></th>
						<td><input type="number" step="0.01" min="0" max="100" id="default_rate" name="default_rate"
							value="<?php echo esc_attr( (string) ( (int) $settings['default_rate_bp'] / 100 ) ); ?>" /></td>
					</tr>
					<tr>
						<th>مبنا</th>
						<td>
							<label><input type="checkbox" name="include_shipping" value="1" <?php checked( (bool) $settings['include_shipping'] ); ?> /> هزینهٔ ارسال هم پورسانت دارد</label><br />
							<label><input type="checkbox" name="include_tax" value="1" <?php checked( (bool) $settings['include_tax'] ); ?> /> مالیات هم در محاسبه لحاظ شود</label>
						</td>
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
							value="<?php echo esc_attr( (string) $settings['exclude_products'] ); ?>" placeholder="101,205" /></td>
					</tr>
					<tr>
						<th><label for="exclude_categories">دسته‌های مستثنا</label></th>
						<td><input type="text" class="regular-text" id="exclude_categories" name="exclude_categories" dir="ltr"
							value="<?php echo esc_attr( (string) $settings['exclude_categories'] ); ?>" placeholder="33" /></td>
					</tr>
					<tr>
						<th><label for="max_commission">سقف پورسانت هر سفارش</label></th>
						<td><input type="number" min="0" id="max_commission" name="max_commission"
							value="<?php echo esc_attr( (string) $settings['max_commission'] ); ?>" />
							<p class="description">صفر = بدون سقف</p></td>
					</tr>
					<tr>
						<th><label for="min_payout">حداقل مبلغ تسویه</label></th>
						<td><input type="number" min="0" id="min_payout" name="min_payout"
							value="<?php echo esc_attr( (string) $settings['min_payout'] ); ?>" /></td>
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
				</table>

				<?php submit_button( 'ذخیره تنظیمات' ); ?>
			</form>
		</div>
		<?php
	}

	/* ------------------------------------------------------------ actions -- */

	public function handleAffiliateStatus(): void {
		$this->guard( 'manage_woocommerce', true );

		$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		$allowed = array(
			AffiliateRepository::STATUS_ACTIVE,
			AffiliateRepository::STATUS_PENDING,
			AffiliateRepository::STATUS_REJECTED,
			AffiliateRepository::STATUS_DISABLED,
		);

		if ( $id > 0 && in_array( $status, $allowed, true ) ) {
			( new AffiliateRepository() )->update( $id, array( 'status' => $status ) );
			$this->redirect( array( 'wap_msg' => 'affiliate_updated' ) );
		}

		$this->redirect( array( 'wap_msg' => 'invalid' ) );
	}

	public function handleCommissionStatus(): void {
		$this->guard( 'manage_woocommerce', true );

		$id     = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$status = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';

		if ( $id > 0 && in_array( $status, Status::all(), true ) ) {
			$repository = new CommissionRepository();
			$row        = $repository->find( $id );

			if ( null !== $row && Status::canTransition( (string) $row['status'], $status ) ) {
				$repository->updateStatus( $id, $status, 'تغییر دستی توسط مدیر' );
				$this->redirect( array( 'page' => self::SLUG . '-commissions', 'wap_msg' => 'commission_updated' ) );
			}
		}

		$this->redirect( array( 'page' => self::SLUG . '-commissions', 'wap_msg' => 'invalid' ) );
	}

	public function handlePayoutStatus(): void {
		$this->guard( 'manage_woocommerce', true );

		$id        = isset( $_POST['id'] ) ? (int) $_POST['id'] : 0;
		$status    = isset( $_POST['status'] ) ? sanitize_key( wp_unslash( $_POST['status'] ) ) : '';
		$reference = isset( $_POST['reference'] ) ? sanitize_text_field( wp_unslash( $_POST['reference'] ) ) : '';

		if ( $id > 0 && in_array( $status, array( PayoutStatus::PAID, PayoutStatus::REJECTED, PayoutStatus::PROCESSING ), true ) ) {
			( new PayoutRepository() )->updateStatus( $id, $status, $reference, 'تغییر وضعیت توسط مدیر' );
			$this->redirect( array( 'page' => self::SLUG . '-payouts', 'wap_msg' => 'payout_updated' ) );
		}

		$this->redirect( array( 'page' => self::SLUG . '-payouts', 'wap_msg' => 'invalid' ) );
	}

	public function handleSaveSettings(): void {
		$this->guard( 'manage_options', true );

		$categoryRates = array();
		$rawRates      = isset( $_POST['category_rates'] ) ? sanitize_textarea_field( wp_unslash( $_POST['category_rates'] ) ) : '';

		foreach ( preg_split( '/\R/', $rawRates ) ?: array() as $line ) {
			$line = trim( $line );
			if ( '' === $line || false === strpos( $line, ':' ) ) {
				continue;
			}
			list( $categoryId, $percent ) = array_map( 'trim', explode( ':', $line, 2 ) );
			if ( is_numeric( $categoryId ) && is_numeric( $percent ) ) {
				$categoryRates[ (int) $categoryId ] = (int) round( (float) $percent * 100 );
			}
		}

		$percent = isset( $_POST['default_rate'] ) ? (float) wp_unslash( $_POST['default_rate'] ) : 10;

		Settings::update(
			array(
				'cookie_days'        => max( 1, min( 365, isset( $_POST['cookie_days'] ) ? (int) $_POST['cookie_days'] : 30 ) ),
				'default_rate_bp'    => max( 0, min( 10000, (int) round( $percent * 100 ) ) ),
				'include_shipping'   => ! empty( $_POST['include_shipping'] ),
				'include_tax'        => ! empty( $_POST['include_tax'] ),
				'category_rates'     => $categoryRates,
				'exclude_products'   => isset( $_POST['exclude_products'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_products'] ) ) : '',
				'exclude_categories' => isset( $_POST['exclude_categories'] ) ? sanitize_text_field( wp_unslash( $_POST['exclude_categories'] ) ) : '',
				'max_commission'     => isset( $_POST['max_commission'] ) ? max( 0, (int) $_POST['max_commission'] ) : 0,
				'min_payout'         => isset( $_POST['min_payout'] ) ? max( 0, (int) $_POST['min_payout'] ) : 0,
				'approve_on'         => ( isset( $_POST['approve_on'] ) && 'processing' === $_POST['approve_on'] ) ? 'processing' : 'completed',
				'auto_approve'       => ! empty( $_POST['auto_approve'] ),
			)
		);

		$this->redirect( array( 'page' => self::SLUG . '-settings', 'wap_msg' => 'settings_saved' ) );
	}

	/* ------------------------------------------------------------ helpers -- */

	private function guard( string $capability, bool $checkNonce = false ): void {
		if ( ! current_user_can( $capability ) ) {
			wp_die(
				esc_html( 'دسترسی مجاز نیست.' ),
				'',
				array( 'response' => 403 )
			);
		}

		if ( $checkNonce ) {
			check_admin_referer( 'wap_action' );
		}
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function url( array $args = array() ): string {
		return add_query_arg( array_merge( array( 'page' => self::SLUG ), $args ), admin_url( 'admin.php' ) );
	}

	/**
	 * @param array<string, mixed> $args
	 */
	private function redirect( array $args ): void {
		wp_safe_redirect( $this->url( $args ) );
		exit;
	}

	private function flash(): void {
		if ( ! isset( $_GET['wap_msg'] ) ) {
			return;
		}

		$messages = array(
			'affiliate_updated'  => 'وضعیت نماینده ذخیره شد.',
			'commission_updated' => 'وضعیت پورسانت ذخیره شد.',
			'payout_updated'     => 'وضعیت تسویه ذخیره شد.',
			'settings_saved'     => 'تنظیمات ذخیره شد.',
			'invalid'            => 'درخواست نامعتبر بود.',
		);

		$key = sanitize_key( wp_unslash( $_GET['wap_msg'] ) );

		if ( ! isset( $messages[ $key ] ) ) {
			return;
		}

		printf(
			'<div class="notice notice-%s is-dismissible"><p>%s</p></div>',
			'invalid' === $key ? 'error' : 'success',
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

		$this->actionButtons( 'wap_affiliate_status', $id, $targets );
	}

	private function commissionActions( int $id, string $status ): void {
		$targets = array();

		foreach ( array( Status::APPROVED => 'تأیید', Status::PAID => 'پرداخت شد', Status::REJECTED => 'رد', Status::REVERSED => 'برگشت' ) as $target => $label ) {
			if ( $target !== $status && Status::canTransition( $status, $target ) ) {
				$targets[ $label ] = $target;
			}
		}

		$this->actionButtons( 'wap_commission_status', $id, $targets );
	}

	private function payoutActions( int $id, string $status ): void {
		$targets = array();

		if ( PayoutStatus::PAID !== $status ) {
			$targets['پرداخت شد'] = PayoutStatus::PAID;
		}
		if ( PayoutStatus::REJECTED !== $status ) {
			$targets['رد'] = PayoutStatus::REJECTED;
		}

		$this->actionButtons( 'wap_payout_status', $id, $targets );
	}

	/**
	 * @param array<string, string> $targets
	 */
	private function actionButtons( string $action, int $id, array $targets ): void {
		foreach ( $targets as $label => $target ) {
			?>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>" style="display:inline-block;margin:0 2px;">
				<?php wp_nonce_field( 'wap_action' ); ?>
				<input type="hidden" name="action" value="<?php echo esc_attr( $action ); ?>" />
				<input type="hidden" name="id" value="<?php echo (int) $id; ?>" />
				<input type="hidden" name="status" value="<?php echo esc_attr( $target ); ?>" />
				<button type="submit" class="button button-small"><?php echo esc_html( $label ); ?></button>
			</form>
			<?php
		}
	}
}
