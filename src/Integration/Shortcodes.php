<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration;

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Domain\Money;
use WooAffiliatePro\Domain\Payout\BalanceCalculator;
use WooAffiliatePro\Domain\Payout\PayoutRequest;
use WooAffiliatePro\Domain\Referral\CodeGenerator;
use WooAffiliatePro\Repository\AffiliateRepository;
use WooAffiliatePro\Repository\ClickRepository;
use WooAffiliatePro\Repository\CommissionRepository;
use WooAffiliatePro\Repository\PayoutRepository;
use WooAffiliatePro\Support\Lock;
use WooAffiliatePro\Support\RateLimiter;
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

	/** @var bool فقط یک‌بار در هر درخواست، حتی اگر شورت‌کد دو بار رندر شود. */
	private $handledPost = false;

	/** @var bool */
	private $assetsEnqueued = false;

	/**
	 * خطاهای فیلدبه‌فیلدِ ثبت‌نام، فقط برای نمایش.
	 *
	 * منطق اعتبارسنجی و پیام کلی تغییری نکرده است؛ این آرایه همان
	 * خطاها را به قالب می‌دهد تا کنار هر فیلد و در خلاصهٔ بالای فرم
	 * (با لینک به همان فیلد) نمایش داده شود.
	 *
	 * @var array<string, string>
	 */
	private $fieldErrors = array();

	/**
	 * مقادیر ارسال‌شدهٔ فرم ثبت‌نام، تا بعد از خطا کاربر مجبور نشود
	 * همهٔ فیلدها را دوباره پر کند.
	 *
	 * @var array<string, string>
	 */
	private $formValues = array();

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

	/**
	 * استایل و اسکریپت فقط در صفحه‌ای که یکی از شورت‌کدها را دارد
	 * بارگذاری می‌شود. اگر شورت‌کد داخل ویجت/بلوک باشد و در محتوای
	 * نوشته پیدا نشود، هنگام رندر دوباره تلاش می‌شود (دیرکرد، ولی
	 * وردپرس استایل‌های دیرهنگام را در فوتر چاپ می‌کند).
	 */
	public function enqueueAssets(): void {
		if ( $this->assetsEnqueued || ! is_singular() ) {
			return;
		}

		$post = get_post();

		if ( $post instanceof \WP_Post
			&& ( has_shortcode( (string) $post->post_content, 'wap_dashboard' )
				|| has_shortcode( (string) $post->post_content, 'wap_join' ) ) ) {
			$this->enqueueNow();
		}
	}

	private function enqueueNow(): void {
		if ( $this->assetsEnqueued ) {
			return;
		}

		$this->assetsEnqueued = true;

		wp_enqueue_style( 'wap-frontend', WAP_URL . 'assets/css/frontend.css', array(), WAP_VERSION );
		wp_enqueue_script( 'wap-frontend-script', WAP_URL . 'assets/js/frontend.js', array(), WAP_VERSION, true );
	}

	public function renderJoin(): string {
		if ( ! is_user_logged_in() ) {
			return $this->renderLoginPrompt();
		}

		$this->enqueueNow();
		$this->handlePost();

		$affiliate = $this->affiliates->findByUserId( get_current_user_id() );

		if ( null !== $affiliate ) {
			if ( '' !== $this->notice && empty( $this->fieldErrors ) ) {
				return $this->noticeHtml();
			}

			return $this->okMessage( 'شما قبلاً ثبت‌نام کرده‌اید.' );
		}

		return $this->noticeHtml() . $this->render(
			'register.php',
			array(
				'notice' => empty( $this->fieldErrors ) ? '' : $this->notice,
				'errors' => $this->fieldErrors,
				'values' => $this->formValues,
			)
		);
	}

	public function renderDashboard(): string {
		if ( ! is_user_logged_in() ) {
			return $this->renderLoginPrompt();
		}

		$this->enqueueNow();
		$this->handlePost();

		$affiliate = $this->affiliates->findByUserId( get_current_user_id() );

		if ( null === $affiliate ) {
			return $this->noticeHtml() . $this->render(
				'register.php',
				array(
					'notice' => empty( $this->fieldErrors ) ? '' : $this->notice,
					'errors' => $this->fieldErrors,
					'values' => $this->formValues,
				)
			);
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
				'minPayout'    => $this->minimumPayout(),
				'currency'     => get_woocommerce_currency_symbol(),
				'decimals'     => $this->priceDecimals(),
				'cookieDays'   => Settings::cookieDays(),
			)
		);
	}

	/**
	 * ثبت‌نام و درخواست تسویه، هر دو از همین تابع رد می‌شوند تا
	 * nonce و capability یک‌جا بررسی شود.
	 *
	 * گارد `$handledPost` جلوی اجرای دوبارهٔ اکشن را وقتی هر دو
	 * شورت‌کد (یا یک شورت‌کد دو بار) در همان صفحه رندر می‌شوند می‌گیرد.
	 * بدون آن، یک رفرش/رندر دوباره می‌توانست درخواست تسویهٔ تکراری بسازد.
	 */
	private function handlePost(): void {
		if ( $this->handledPost || 'POST' !== ( $_SERVER['REQUEST_METHOD'] ?? '' ) ) {
			return;
		}

		$this->handledPost = true;

		$action = sanitize_key( $this->posted( 'wap_action' ) );

		if ( 'register' === $action ) {
			$this->handleRegister();

			return;
		}

		if ( 'payout' === $action ) {
			$this->handlePayout();
		}
	}

	private function handleRegister(): void {
		$nonce = $this->posted( 'wap_register_nonce' );

		if ( '' === $nonce || ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wap_register' ) ) {
			$this->fail( 'درخواست نامعتبر است. صفحه را دوباره بارگذاری کنید.' );

			return;
		}

		$userId = get_current_user_id();

		if ( null !== $this->affiliates->findByUserId( $userId ) ) {
			$this->notice     = 'شما قبلاً ثبت‌نام کرده‌اید.';
			$this->noticeType = 'info';

			return;
		}

		$this->formValues = array();

		if ( ! RateLimiter::allow( 'register:' . $userId . ':' . $this->clientIp(), 5, 10 * MINUTE_IN_SECONDS ) ) {
			$this->fail( 'تعداد تلاش‌ها زیاد است. چند دقیقهٔ دیگر دوباره امتحان کنید.' );

			return;
		}

		$lockKey = 'register:' . $userId;

		if ( ! Lock::acquire( $lockKey, 15 ) ) {
			$this->fail( 'درخواست قبلی شما در حال پردازش است. چند لحظه بعد دوباره تلاش کنید.' );

			return;
		}

		$keepLock = false;

		try {
			$name    = sanitize_text_field( $this->posted( 'wap_name' ) );
			$mobile  = sanitize_text_field( $this->posted( 'wap_mobile' ) );
			$method  = sanitize_key( $this->posted( 'wap_payout_method' ) );
			$details = sanitize_textarea_field( $this->posted( 'wap_payout_details' ) );

			if ( '' === $method ) {
				$method = 'card';
			}

			$name    = mb_substr( trim( $name ), 0, 190 );
			$mobile  = preg_replace( '/[\s\-()]+/u', '', $this->latinDigits( $mobile ) ) ?: '';
			$details = mb_substr( trim( $details ), 0, 200 );

			$this->formValues = array(
				'wap_name'           => $name,
				'wap_mobile'         => $mobile,
				'wap_payout_method'  => $method,
				'wap_payout_details' => $details,
			);

			if ( '' === $name || ! preg_match( '/^09\d{9}$/', $mobile ) ) {
				if ( '' === $name ) {
					$this->fieldErrors['wap_name'] = 'نام و نام خانوادگی را وارد کنید.';
				}

				if ( ! preg_match( '/^09\d{9}$/', $mobile ) ) {
					$this->fieldErrors['wap_mobile'] = 'شمارهٔ موبایل باید 11 رقم باشد و با 09 شروع شود.';
				}

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

			$created = $this->affiliates->create(
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

			if ( $created <= 0 ) {
				$this->fail( 'ثبت‌نام انجام نشد. لطفاً دوباره تلاش کنید.' );

				return;
			}

			$this->notice     = Settings::bool( 'auto_approve' )
				? 'ثبت‌نام انجام شد. لینک اختصاصی شما فعال است.'
				: 'ثبت‌نام انجام شد و در انتظار تأیید است.';
			$this->noticeType = 'success';
			$keepLock         = true;
		} catch ( \Throwable $e ) {
			$this->fail( 'ثبت‌نام انجام نشد. لطفاً دوباره تلاش کنید.' );
			$this->log( 'registration_failed', $e );
		} finally {
			if ( ! $keepLock ) {
				Lock::release( $lockKey );
			}
		}
	}

	private function handlePayout(): void {
		$nonce = $this->posted( 'wap_payout_nonce' );

		if ( '' === $nonce || ! wp_verify_nonce( sanitize_text_field( $nonce ), 'wap_payout' ) ) {
			$this->fail( 'درخواست نامعتبر است.' );

			return;
		}

		$affiliate = $this->affiliates->findByUserId( get_current_user_id() );

		if ( null === $affiliate || AffiliateRepository::STATUS_ACTIVE !== $affiliate['status'] ) {
			$this->fail( 'حساب نمایندگی فعالی برای شما پیدا نشد.' );

			return;
		}

		$affiliateId = (int) $affiliate['id'];

		if ( ! RateLimiter::allow( 'payout:' . $affiliateId, 10, MINUTE_IN_SECONDS ) ) {
			$this->fail( 'تعداد درخواست‌ها زیاد است. یک دقیقهٔ دیگر دوباره تلاش کنید.' );

			return;
		}

		// قفل کوتاه تا دو کلیک/دو رفرش پشت‌سرهم دو درخواست نسازد.
		// قفل پس از موفقیت عمداً آزاد نمی‌شود و با TTL خودش منقضی می‌شود.
		if ( ! Lock::acquire( 'payout:' . $affiliateId, 30 ) ) {
			$this->fail( 'درخواست تسویهٔ قبلی شما در حال ثبت است. چند لحظه بعد دوباره تلاش کنید.' );

			return;
		}

		$amountRaw = '' !== $this->posted( 'wap_amount' ) ? sanitize_text_field( $this->posted( 'wap_amount' ) ) : '0';
		$currency  = get_woocommerce_currency();
		$decimals  = $this->priceDecimals();

		try {
			$amount = Money::fromString( $amountRaw, $currency, $decimals );
		} catch ( \InvalidArgumentException $e ) {
			Lock::release( 'payout:' . $affiliateId );
			$this->fail( 'مبلغ وارد‌شده معتبر نیست.' );

			return;
		}

		$balance = $this->balanceFor( $affiliateId );
		$minimum = $this->minimumPayout();
		$request = new PayoutRequest( $amount, (string) $affiliate['payout_method'] );
		$errors  = $request->validate( $balance, $minimum );

		if ( ! empty( $errors ) ) {
			Lock::release( 'payout:' . $affiliateId );
			$this->notice     = implode( ' ', array_values( $errors ) );
			$this->noticeType = 'error';

			return;
		}

		$created = $this->payouts->create(
			array(
				'affiliate_id' => $affiliateId,
				'amount'       => $amount->amount(),
				'method'       => (string) $affiliate['payout_method'],
			)
		);

		if ( $created <= 0 ) {
			Lock::release( 'payout:' . $affiliateId );
			$this->fail( 'ثبت درخواست تسویه انجام نشد. لطفاً دوباره تلاش کنید.' );

			return;
		}

		$this->notice     = 'درخواست تسویه ثبت شد و پس از بررسی پرداخت می‌شود.';
		$this->noticeType = 'success';
	}

	private function balanceFor( int $affiliateId ): \WooAffiliatePro\Domain\Payout\Balance {
		return ( new BalanceCalculator() )->calculate(
			$this->commissions->rowsForAffiliate( $affiliateId ),
			$this->payouts->rowsForAffiliate( $affiliateId ),
			get_woocommerce_currency(),
			$this->priceDecimals()
		);
	}

	private function minimumPayout(): Money {
		// مقدار تنظیمات مثل پورسانت‌ها در کوچک‌ترین واحد ذخیره می‌شود؛ fromString
		// آن را به‌عنوان مبلغ اصلی تفسیر می‌کرد و با ارزهای اعشاری اشتباه می‌شد.
		return Money::of(
			max( 0, Settings::int( 'min_payout' ) ),
			get_woocommerce_currency(),
			$this->priceDecimals()
		);
	}

	private function priceDecimals(): int {
		return function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 0;
	}

	/**
	 * @param array<string, mixed> $vars
	 */
	private function render( string $template, array $vars ): string {
		$file = WAP_PATH . 'templates/' . $template;

		if ( ! is_readable( $file ) ) {
			return '';
		}

		ob_start();

		// متغیرها به‌صورت صریح تعریف می‌شوند؛ از استخراج خودکار پرهیز شده است.
		switch ( $template ) {
			case 'register.php':
				$notice = (string) ( $vars['notice'] ?? '' );
				$errors = is_array( $vars['errors'] ?? null ) ? $vars['errors'] : array();
				$values = is_array( $vars['values'] ?? null ) ? $vars['values'] : array();
				include $file;
				break;

			case 'dashboard.php':
				$affiliate   = is_array( $vars['affiliate'] ?? null ) ? $vars['affiliate'] : array();
				$balance     = $vars['balance'] ?? null;
				$refUrl      = (string) ( $vars['refUrl'] ?? '' );
				$clicks      = (int) ( $vars['clicks'] ?? 0 );
				$clicks30    = (int) ( $vars['clicks30'] ?? 0 );
				$commissions = is_array( $vars['commissions'] ?? null ) ? $vars['commissions'] : array();
				$payouts     = is_array( $vars['payouts'] ?? null ) ? $vars['payouts'] : array();
				$minPayout   = $vars['minPayout'] ?? Money::zero();
				$currency    = (string) ( $vars['currency'] ?? '' );
				$decimals    = (int) ( $vars['decimals'] ?? 0 );
				include $file;
				break;

			case 'notice.php':
				$message = (string) ( $vars['message'] ?? '' );
				$type    = (string) ( $vars['type'] ?? 'info' );
				include $file;
				break;

			default:
				ob_end_clean();

				return '';
		}

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
		// وقتی خطای فیلد داریم، خلاصهٔ خطای داخل فرم (با لینک به هر فیلد)
		// همان پیام را یک‌بار و به‌صورت قابل‌فوکوس نشان می‌دهد؛ آگاه‌سازی
		// دوبارهٔ صفحه‌خوان با دو ناحیهٔ زنده درست نیست.
		if ( '' === $this->notice || ! empty( $this->fieldErrors ) ) {
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

	private function fail( string $message ): void {
		$this->notice     = $message;
		$this->noticeType = 'error';
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

	/**
	 * خواندن یک مقدار اسکالر از POST؛ آرایه هرگز به sanitizer نمی‌رسد.
	 */
	private function posted( string $key ): string {
		if ( ! isset( $_POST[ $key ] ) || ! is_scalar( $_POST[ $key ] ) ) {
			return '';
		}

		return (string) wp_unslash( $_POST[ $key ] );
	}

	private function clientIp(): string {
		$value = isset( $_SERVER['REMOTE_ADDR'] ) && is_scalar( $_SERVER['REMOTE_ADDR'] )
			? sanitize_text_field( wp_unslash( $_SERVER['REMOTE_ADDR'] ) )
			: '';

		return filter_var( $value, FILTER_VALIDATE_IP ) ? $value : '0.0.0.0';
	}

	private function log( string $event, \Throwable $error ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error(
				sprintf( '%s: %s', $event, $error->getMessage() ),
				array( 'source' => 'woo-affiliate-pro' )
			);
		}
	}
}
