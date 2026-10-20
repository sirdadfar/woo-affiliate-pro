<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration;

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Domain\Commission\Calculator;
use WooAffiliatePro\Domain\Commission\CommissionPolicy;
use WooAffiliatePro\Domain\Commission\Status;
use WooAffiliatePro\Domain\Money;
use WooAffiliatePro\Domain\Order\OrderLine;
use WooAffiliatePro\Domain\Order\OrderSnapshot;
use WooAffiliatePro\Domain\Referral\CookiePolicy;
use WooAffiliatePro\Repository\AffiliateRepository;
use WooAffiliatePro\Repository\CommissionRepository;
use WooAffiliatePro\Support\Settings;

/**
 * اتصال چرخهٔ سفارش ووکامرس به منطق پورسانت.
 *
 * دو نکتهٔ مهم که اکثر افزونه‌های مشابه اشتباه انجام می‌دهند:
 *
 * ۱) کد معرف در لحظهٔ *ساخت سفارش* روی سفارش ذخیره می‌شود، نه در لحظهٔ
 *    تکمیل. بین این دو ممکن است روزها بگذرد (درگاه بانکی، پرداخت آفلاین،
 *    سفارش تلفنی) و کوکی کاربر تا آن موقع منقضی شده باشد.
 *
 * ۲) پورسانت با وضعیت pending ساخته می‌شود و فقط بعد از تکمیل سفارش
 *    approve می‌شود. اگر سفارش لغو یا مسترد شد، همان رکورد reverse می‌شود
 *    تا سابقه حفظ شود و موجودی نماینده با حذف رکورد، بی‌سر‌و‌صدا بالا نرود.
 */
final class OrderListener {

	public const META_AFFILIATE = '_wap_affiliate_id';
	public const META_CODE      = '_wap_ref_code';
	public const META_TOTAL     = '_wap_commission_total';
	public const META_SELF      = '_wap_self_referral';

	/** @var AffiliateRepository */
	private $affiliates;

	/** @var CommissionRepository */
	private $commissions;

	public function __construct( ?AffiliateRepository $affiliates = null, ?CommissionRepository $commissions = null ) {
		$this->affiliates  = $affiliates ?: new AffiliateRepository();
		$this->commissions = $commissions ?: new CommissionRepository();
	}

	public function register(): void {
		add_action( 'woocommerce_checkout_create_order', array( $this, 'attachAffiliate' ), 10, 2 );

		// چک‌اوت بلوکی (Store API) از مسیر کلاسیک `WC_Checkout` رد نمی‌شود،
		// پس هوک قدیمی به‌تنهایی کافی نیست. این هوک نقطهٔ رسمی افزودن متادیتا
		// به سفارش در چک‌اوت بلوکی است و کوکی معرف در همان درخواست در دسترس است.
		add_action( 'woocommerce_store_api_checkout_update_order_from_request', array( $this, 'attachAffiliateFromBlocks' ), 10, 2 );

		add_action( 'woocommerce_order_status_changed', array( $this, 'onStatusChange' ), 10, 4 );
		add_action( 'woocommerce_order_refunded', array( $this, 'onRefund' ), 10, 2 );
	}

	/**
	 * نسخهٔ چک‌اوت بلوکی؛ درخواست REST استفاده نمی‌شود، فقط سفارش.
	 *
	 * @param \WC_Order            $order
	 * @param \WP_REST_Request     $request
	 */
	public function attachAffiliateFromBlocks( $order, $request = null ): void {
		if ( ! $order instanceof \WC_Order ) {
			return;
		}

		if ( (int) $order->get_meta( self::META_AFFILIATE ) > 0 ) {
			return;
		}

		$this->attachAffiliate( $order, array() );
	}

	/**
	 * در لحظهٔ ساخت سفارش، کد معرف را روی سفارش می‌نویسد.
	 *
	 * @param \WC_Order $order
	 * @param array<string, mixed> $data
	 */
	public function attachAffiliate( $order, $data ): void {
		$tracker = new ReferralTracker();
		$code    = $tracker->currentCode();

		if ( null === $code ) {
			return;
		}

		$affiliate = $this->affiliates->findByCode( $code );

		if ( null === $affiliate || AffiliateRepository::STATUS_ACTIVE !== $affiliate['status'] ) {
			return;
		}

		$policy = new CookiePolicy();

		$customer = array(
			'user_id' => (int) $order->get_customer_id(),
			'email'   => (string) $order->get_billing_email(),
		);

		if ( $policy->isSelfReferral(
			array(
				'user_id' => (int) $affiliate['user_id'],
				'email'   => (string) $affiliate['email'],
			),
			$customer
		) ) {
			$order->update_meta_data( self::META_SELF, '1' );
			$order->add_order_note( 'کد معرف نادیده گرفته شد: خرید از لینک خودِ نماینده.' );

			return;
		}

		$order->update_meta_data( self::META_AFFILIATE, (int) $affiliate['id'] );
		$order->update_meta_data( self::META_CODE, $code );
	}

	/**
	 * @param int       $orderId
	 * @param string    $from
	 * @param string    $to
	 * @param \WC_Order $order
	 */
	public function onStatusChange( $orderId, $from, $to, $order ): void {
		$affiliateId = (int) $order->get_meta( self::META_AFFILIATE );

		if ( $affiliateId <= 0 ) {
			return;
		}

		if ( in_array( $to, array( 'pending', 'on-hold', 'processing' ), true ) ) {
			$this->createCommission( $order, $affiliateId );

			if ( $this->isApprovalStatus( $to ) ) {
				$this->approve( $orderId );
			}

			return;
		}

		if ( 'completed' === $to ) {
			$this->createCommission( $order, $affiliateId );
			$this->approve( $orderId );

			return;
		}

		if ( in_array( $to, array( 'cancelled', 'refunded' ), true ) ) {
			$this->reverse( $orderId, sprintf( 'سفارش به وضعیت «%s» تغییر کرد.', $to ) );
		}

		// وضعیت «failed» عمداً برگشت نمی‌خورد: مشتری می‌تواند دوباره پرداخت کند
		// و همان سفارش به processing برود. اگر اینجا reverse می‌کردیم، پورسانت
		// برای همیشه از دست می‌رفت چون وضعیت برگشت‌خورده نهایی است.
	}

	/**
	 * @param int $orderId
	 * @param int $refundId
	 */
	public function onRefund( $orderId, $refundId ): void {
		$order = wc_get_order( $orderId );

		if ( ! $order ) {
			return;
		}

		$currency = (string) $order->get_currency();
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 0;

		// مقایسه در واحد پول صحیح انجام می‌شود، نه با اختلاف شناور ۰.۹۹.
		$refundTotal = Money::fromFloat( (float) $order->get_total_refunded(), $currency, $decimals )->amount();
		$orderTotal  = Money::fromFloat( (float) $order->get_total(), $currency, $decimals )->amount();

		// استرداد جزئی، پورسانت را کامل باطل نمی‌کند؛ فقط استرداد کامل باعث برگشت می‌شود.
		if ( $orderTotal > 0 && $refundTotal >= $orderTotal ) {
			$this->reverse( (int) $orderId, 'کل مبلغ سفارش مسترد شد.' );
		}
	}

	private function isApprovalStatus( string $status ): bool {
		$approveOn = Settings::string( 'approve_on' );

		return $approveOn === $status;
	}

	private function createCommission( $order, int $affiliateId ): void {
		$orderId = (int) $order->get_id();

		if ( $this->commissions->existsForOrder( $orderId ) ) {
			return;
		}

		$affiliate = $this->affiliates->find( $affiliateId );

		if ( null === $affiliate ) {
			return;
		}

		try {
			$policy   = $this->buildPolicy( $affiliate, (string) $order->get_currency() );
			$snapshot = $this->buildSnapshot( $order );

			if ( null === $snapshot ) {
				return;
			}

			$breakdown = ( new Calculator() )->calculate( $snapshot, $policy );

			if ( $breakdown->total()->isZero() || $breakdown->total()->isNegative() ) {
				return;
			}

			$created = $this->commissions->create(
				array(
					'affiliate_id'      => $affiliateId,
					'order_id'          => $orderId,
					'amount'            => $breakdown->total()->amount(),
					'base_amount'       => $this->orderBaseTotal( $order ),
					'rate_basis_points' => (int) $affiliate['rate_basis_points'],
					'currency'          => $order->get_currency(),
					'status'            => Status::PENDING,
					'note'              => (string) wp_json_encode( $breakdown->toArray() ),
				)
			);

			if ( $created <= 0 ) {
				// کلید یکتای (order_id, affiliate_id) ممکن است در درخواست هم‌زمان برنده شده باشد.
				$this->log( sprintf( 'ثبت پورسانت سفارش #%d انجام نشد (احتمال ثبت هم‌زمان).', $orderId ) );

				return;
			}

			$order->update_meta_data( self::META_TOTAL, $breakdown->total()->amount() );
			$order->save();

			$order->add_order_note(
				sprintf(
					'پورسانت نماینده ثبت شد: %s (نماینده #%d)',
					$breakdown->total()->format(),
					$affiliateId
				)
			);
		} catch ( \Throwable $e ) {
			// خطای محاسبه یا ذخیره نباید پرداخت/تکمیل سفارش را بشکند؛ برای بررسی لاگ می‌شود.
			$this->log( sprintf( 'محاسبهٔ پورسانت سفارش #%d ناموفق بود: %s', $orderId, $e->getMessage() ) );
		}
	}

	private function approve( int $orderId ): void {
		$row = $this->commissions->findByOrder( $orderId );

		if ( null === $row || Status::PAID === $row['status'] ) {
			return;
		}

		try {
			if ( Status::canTransition( (string) $row['status'], Status::APPROVED ) ) {
				$this->commissions->updateStatus( (int) $row['id'], Status::APPROVED );
			}
		} catch ( \InvalidArgumentException $e ) {
			// وضعیت ناشناخته در ردیف قدیمی نباید تغییر وضعیت سفارش را بشکند.
			$this->log( sprintf( 'تأیید پورسانت سفارش #%d ممکن نبود: %s', $orderId, $e->getMessage() ) );
		}
	}

	private function reverse( int $orderId, string $note ): void {
		$row = $this->commissions->findByOrder( $orderId );

		if ( null === $row ) {
			return;
		}

		try {
			if ( Status::canTransition( (string) $row['status'], Status::REVERSED ) ) {
				$this->commissions->updateStatus( (int) $row['id'], Status::REVERSED, $note );
			}
		} catch ( \InvalidArgumentException $e ) {
			$this->log( sprintf( 'برگشت پورسانت سفارش #%d ممکن نبود: %s', $orderId, $e->getMessage() ) );
		}
	}

	/**
	 * @param array<string, mixed> $affiliate
	 */
	private function buildPolicy( array $affiliate, string $currency ): CommissionPolicy {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 0;
		$rateBp   = (int) $affiliate['rate_basis_points'];
		if ( $rateBp <= 0 ) {
			$rateBp = Settings::int( 'default_rate_bp' );
		}

		$categoryRates = array();
		$rawRates      = Settings::get( 'category_rates' );

		if ( is_array( $rawRates ) ) {
			foreach ( $rawRates as $categoryId => $value ) {
				$categoryRates[ (int) $categoryId ] = array(
					'type'  => CommissionPolicy::TYPE_PERCENT,
					'value' => (int) $value,
				);
			}
		}

		$max = Settings::int( 'max_commission' );

		return new CommissionPolicy(
			CommissionPolicy::TYPE_PERCENT,
			$rateBp,
			null,
			Settings::bool( 'include_shipping' ),
			Settings::bool( 'include_tax' ),
			$categoryRates,
			Settings::idList( 'exclude_products' ),
			$max > 0 ? Money::of( $max, $currency, $decimals ) : null,
			Settings::idList( 'exclude_categories' )
		);
	}

	private function buildSnapshot( $order ): ?OrderSnapshot {
		$lines = array();

		foreach ( $order->get_items() as $item ) {
			if ( ! $item instanceof \WC_Order_Item_Product ) {
				continue;
			}

			$productId = (int) $item->get_product_id();
			$terms     = $productId > 0 ? wp_get_post_terms( $productId, 'product_cat', array( 'fields' => 'ids' ) ) : array();
			$categoryIds = is_array( $terms ) ? array_map( 'intval', $terms ) : array();

			$lineTotal = (float) $item->get_total();
			$lineTax   = (float) $item->get_total_tax();

			$lines[] = new OrderLine(
				$productId,
				(string) $item->get_name(),
				$categoryIds,
				Money::fromFloat( $lineTotal, $order->get_currency(), wc_get_price_decimals() ),
				Money::fromFloat( $lineTax, $order->get_currency(), wc_get_price_decimals() ),
				max( 1, (int) $item->get_quantity() )
			);
		}

		if ( empty( $lines ) ) {
			return null;
		}

		return new OrderSnapshot(
			(int) $order->get_id(),
			$lines,
			Money::fromFloat( (float) $order->get_shipping_total(), $order->get_currency(), wc_get_price_decimals() ),
			Money::fromFloat( (float) $order->get_discount_total(), $order->get_currency(), wc_get_price_decimals() ),
			$order->get_currency(),
			wc_get_price_decimals()
		);
	}

	private function log( string $message ): void {
		if ( function_exists( 'wc_get_logger' ) ) {
			wc_get_logger()->error( $message, array( 'source' => 'woo-affiliate-pro' ) );
		}
	}

	private function orderBaseTotal( $order ): int {
		$total = 0.0;

		foreach ( $order->get_items() as $item ) {
			if ( $item instanceof \WC_Order_Item_Product ) {
				$total += (float) $item->get_total();
			}
		}

		return Money::fromFloat( $total, $order->get_currency(), wc_get_price_decimals() )->amount();
	}
}
