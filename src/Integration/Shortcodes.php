<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration;

use WooAffiliatePro\Domain\Money;
use WooAffiliatePro\Domain\Payout\BalanceCalculator;
use WooAffiliatePro\Domain\Payout\PayoutRequest;
use WooAffiliatePro\Domain\Referral\CodeGenerator;
use WooAffiliatePro\Repository\AffiliateRepository;
use WooAffiliatePro\Repository\ClickRepository;
use WooAffiliatePro\Repository\CommissionRepository;
use WooAffiliatePro\Repository\PayoutRepository;
use WooAffiliatePro\Support\Settings;

/**
 * شورت‌کدهای پنل نماینده:  [wap_dashboard]  و  [wap_join]
 *
 * منطق در همین کلاس است و مارک‌آپ در پوشهٔ templates تا کاربر بتواند
 * ظاهر را بدون دست زدن به منطق مالی تغییر دهد.
 */
final class Shortcodes {

	/** @var AffiliateRepository */
	private $affiliates;

	/** @var CommissionRepository */
	private $commissions;

	/** @var PayoutRepository */
	private $payouts;

	/** @var ClickRepository */
	private $clicks;

	/** @var string */
	private $notice = '';

	/** @var string */
	private $noticeType = 'info';

	public function __construct() {
		$this->affiliates  = new AffiliateRepository();
		$this->commissions = new CommissionRepository();
		$this->payouts     = new PayoutRepository();
		$this->clicks      = new ClickRepository();
	}

	public function register(): void {
		add_shortcode( 'wap_dashboard', array( $this, 'renderDashboard' ) );
		add_shortcode( 'wap_join', array( $this, 'renderJoin' ) );
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueueAssets' ) );
	}

	public function enqueueAssets(): void {
		wp_enqueue_style( 'wap-frontend', WAP_URL . 'assets/css/frontend.css', array(), WAP_VERSION );
	}

	public function renderJoin(): string {
		if ( ! is_user_logged_in() ) {
			return $this->renderLoginPrompt();
		}

		$this->handlePost();

		$affiliate = $this->affiliates->findByUserId( get_current_user_id() );

		if ( null !== $affiliate ) {
			return $this->noticeHtml() . $this->okMessage( 'شما قبلاً ثبت‌نام کرده‌اید.' );
		}

		return $this->noticeHtml() . $this->render( 'register.php', array( 'notice' => '' ) );
	}

	public function renderDashboard(): string {
		if ( ! is_user_logged_in() ) {
			return $this->renderLoginPrompt();
		}

		$this->handlePost();

		$affiliate = $this->affiliates->findByUserId( get_current_user_id() );

		if ( null === $affiliate ) {
			return $this->noticeHtml() . $this->render( 'register.php', array( 'notice' => '' ) );
		}

		switch ( $affiliate['status'] ) {
			case AffiliateRepository::STATUS_REJECTED:
				return $this->noticeHtml() . $this->warningMessage( 'درخواست همکاری شما رد شده است. برای پیگیری با پشتیبانی تماس بگیرید.' );

			case AffiliateRepository::STATUS_DISABLED:
				return $this->noticeHtml() . $this->warningMessage( 'حساب نمایندگی شما غیرفعال است.' );

			case AffiliateRepository::STATUS_PENDING:
				return $this->noticeHtml() . $this->infoMessage( 'درخواست شما ثبت شده و در انتظار تأیید است. پس از تأیید، لینک اختصاصی شما فعال می‌شود.' );
		}

		$balance = $this->balanceFor( (int) $affiliate['id'] );

		return $this->noticeHtml() . $this->render(
			'dashboard.php',
			array(
				'affiliate'    => $affiliate,
				'balance'      => $balance,
				'refUrl'       => CodeGenerator::toUrlParam( (string) $affiliate['code'], home_url( '/' ) ),
				'clicks'       => $this->clicks->countForAffiliate( (int) $affiliate['id'] ),
				'clicks30'     => $this->clicks->countSince( (int) $affiliate['id'], time() - ( 30 * 86400 ) ),
				'commissions'  => $this->commissions->forAffiliate( (int) $affiliate['id'], 20 ),
				'payouts'      => $this->payouts->forAffiliate( (int) $affiliate['id'], 10 ),
				'minPayout'    => Money::of( Settings::int( 'min_payout' ) ),
				'currency'     => get_woocommerce_currency_symbol(),
			)
		);
	}

	/**
	 * ثبت‌نام و درخواست تسویه، هر دو از همین تابع رد می‌شوند تا
	 * nonce و capability یک‌جا بررسی شود.
	 */
	private function handlePost(): void {
		if ( 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		if ( isset( $_POST['wap_action'] ) && 'register' === $_POST['wap_action'] ) {
			$this->handleRegister();

			return;
		}

		if ( isset( $_POST['wap_action'] ) && 'payout' === $_POST['wap_action'] ) {
			$this->handlePayout();
		}
	}

	private function handleRegister(): void {
		if ( ! isset( $_POST['wap_register_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wap_register_nonce'] ) ), 'wap_register' ) ) {
			$this->notice      = 'درخواست نامعتبر است. صفحه را دوباره بارگذاری کنید.';
			$this->noticeType  = 'error';

			return;
		}

		$userId = get_current_user_id();

		if ( null !== $this->affiliates->findByUserId( $userId ) ) {
			$this->notice     = 'شما قبلاً ثبت‌نام کرده‌اید.';
			$this->noticeType = 'info';

			return;
		}

		$name   = isset( $_POST['wap_name'] ) ? sanitize_text_field( wp_unslash( $_POST['wap_name'] ) ) : '';
		$mobile = isset( $_POST['wap_mobile'] ) ? sanitize_text_field( wp_unslash( $_POST['wap_mobile'] ) ) : '';
		$method = isset( $_POST['wap_payout_method'] ) ? sanitize_key( wp_unslash( $_POST['wap_payout_method'] ) ) : 'card';
		$details = isset( $_POST['wap_payout_details'] ) ? sanitize_textarea_field( wp_unslash( $_POST['wap_payout_details'] ) ) : '';

		if ( '' === $name || ! preg_match( '/^09\d{9}$/', $mobile ) ) {
			$this->notice     = 'نام و شمارهٔ موبایل (مثل 09121234567) را درست وارد کنید.';
			$this->noticeType = 'error';

			return;
		}

		if ( ! in_array( $method, PayoutRequest::METHODS, true ) ) {
			$method = 'card';
		}

		$generator = new CodeGenerator( array( $this->affiliates, 'codeExists' ) );
		$email     = (string) wp_get_current_user()->user_email;

		if ( null !== $this->affiliates->findByEmail( $email ) ) {
			$this->notice     = 'با این ایمیل قبلاً ثبت‌نام شده است.';
			$this->noticeType = 'error';

			return;
		}

		$this->affiliates->create(
			array(
				'user_id'           => $userId,
				'code'              => $generator->generate( 8 ),
				'name'              => $name,
				'email'             => $email,
				'mobile'            => $mobile,
				'status'            => Settings::bool( 'auto_approve' )
					? AffiliateRepository::STATUS_ACTIVE
					: AffiliateRepository::STATUS_PENDING,
				'rate_basis_points' => Settings::int( 'default_rate_bp' ),
				'payout_method'     => $method,
				'payout_details'    => $details,
			)
		);

		$this->notice     = Settings::bool( 'auto_approve' )
			? 'ثبت‌نام انجام شد. لینک اختصاصی شما فعال است.'
			: 'ثبت‌نام انجام شد و در انتظار تأیید است.';
		$this->noticeType = 'success';
	}

	private function handlePayout(): void {
		if ( ! isset( $_POST['wap_payout_nonce'] )
			|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['wap_payout_nonce'] ) ), 'wap_payout' ) ) {
			$this->notice     = 'درخواست نامعتبر است.';
			$this->noticeType = 'error';

			return;
		}

		$affiliate = $this->affiliates->findByUserId( get_current_user_id() );

		if ( null === $affiliate || AffiliateRepository::STATUS_ACTIVE !== $affiliate['status'] ) {
			$this->notice     = 'حساب نمایندگی فعالی برای شما پیدا نشد.';
			$this->noticeType = 'error';

			return;
		}

		$amountRaw = isset( $_POST['wap_amount'] ) ? sanitize_text_field( wp_unslash( $_POST['wap_amount'] ) ) : '0';
		$amountRaw = str_replace( array( ',', ' ' ), '', $amountRaw );

		if ( ! is_numeric( $amountRaw ) ) {
			$this->notice     = 'مبلغ وارد‌شده معتبر نیست.';
			$this->noticeType = 'error';

			return;
		}

		$amount   = Money::of( (int) $amountRaw, get_woocommerce_currency() );
		$balance  = $this->balanceFor( (int) $affiliate['id'] );
		$minimum  = Money::of( Settings::int( 'min_payout' ), get_woocommerce_currency() );
		$request  = new PayoutRequest( $amount, (string) $affiliate['payout_method'] );
		$errors   = $request->validate( $balance, $minimum );

		if ( ! empty( $errors ) ) {
			$this->notice     = implode( ' ', array_values( $errors ) );
			$this->noticeType = 'error';

			return;
		}

		$this->payouts->create(
			array(
				'affiliate_id' => (int) $affiliate['id'],
				'amount'       => $amount->amount(),
				'method'       => (string) $affiliate['payout_method'],
			)
		);

		$this->notice     = 'درخواست تسویه ثبت شد و پس از بررسی پرداخت می‌شود.';
		$this->noticeType = 'success';
	}

	private function balanceFor( int $affiliateId ): \WooAffiliatePro\Domain\Payout\Balance {
		return ( new BalanceCalculator() )->calculate(
			$this->commissions->rowsForAffiliate( $affiliateId ),
			$this->payouts->rowsForAffiliate( $affiliateId ),
			get_woocommerce_currency()
		);
	}

	/**
	 * @param array<string, mixed> $vars
	 */
	private function render( string $template, array $vars ): string {
		$file = WAP_PATH . 'templates/' . $template;

		if ( ! file_exists( $file ) ) {
			return '';
		}

		ob_start();
		// phpcs:ignore WordPress.PHP.DontExtract.extract_extract -- متغیرها کنترل‌شده و داخلی هستند.
		extract( $vars, EXTR_SKIP );
		include $file;

		return (string) ob_get_clean();
	}

	private function renderLoginPrompt(): string {
		return $this->infoMessage(
			sprintf(
				'برای دیدن پنل همکاری در فروش ابتدا <a href="%s">وارد حساب کاربری</a> شوید.',
				esc_url( wc_get_page_permalink( 'myaccount' ) )
			)
		);
	}

	private function noticeHtml(): string {
		if ( '' === $this->notice ) {
			return '';
		}

		return $this->render(
			'notice.php',
			array(
				'message' => $this->notice,
				'type'    => $this->noticeType,
			)
		);
	}

	private function infoMessage( string $text ): string {
		return $this->render( 'notice.php', array( 'message' => $text, 'type' => 'info' ) );
	}

	private function okMessage( string $text ): string {
		return $this->render( 'notice.php', array( 'message' => $text, 'type' => 'success' ) );
	}

	private function warningMessage( string $text ): string {
		return $this->render( 'notice.php', array( 'message' => $text, 'type' => 'error' ) );
	}
}
