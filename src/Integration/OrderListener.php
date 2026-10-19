<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration;

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
		add_action( 'woocommerce_order_status_changed', array( $this, 'onStatusChange' ), 10, 4 );
		add_action( 'woocommerce_order_refunded', array( $this, 'onRefund' ), 10, 2 );
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

		if ( in_array( $to, array( 'cancelled', 'refunded', 'failed' ), true ) ) {
			$this->reverse( $orderId, sprintf( 'سفارش به وضعیت «%s» تغییر کرد.', $to ) );
		}
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

		$refundTotal = (float) $order->get_total_refunded();
		$orderTotal  = (float) $order->get_total();

		// استرداد جزئی، پورسانت را کامل باطل نمی‌کند؛ فقط استرداد کامل باعث برگشت می‌شود.
		if ( $orderTotal > 0 && $refundTotal >= $orderTotal * 0.99 ) {
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

		$policy = $this->buildPolicy( $affiliate );
		$snapshot = $this->buildSnapshot( $order );

		if ( null === $snapshot ) {
			return;
		}

		$breakdown = ( new Calculator() )->calculate( $snapshot, $policy );

		if ( $breakdown->total()->isZero() || $breakdown->total()->isNegative() ) {
			return;
		}

		$this->commissions->create(
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

		$order->update_meta_data( self::META_TOTAL, $breakdown->total()->amount() );
		$order->save();

		$order->add_order_note(
			sprintf(
				'پورسانت نماینده ثبت شد: %s (نماینده #%d)',
				$breakdown->total()->format(),
				$affiliateId
			)
		);
	}

	private function approve( int $orderId ): void {
		$row = $this->commissions->findByOrder( $orderId );

		if ( null === $row || Status::PAID === $row['status'] ) {
			return;
		}

		if ( Status::canTransition( (string) $row['status'], Status::APPROVED ) ) {
			$this->commissions->updateStatus( (int) $row['id'], Status::APPROVED );
		}
	}

	private function reverse( int $orderId, string $note ): void {
		$row = $this->commissions->findByOrder( $orderId );

		if ( null === $row ) {
			return;
		}

		if ( Status::canTransition( (string) $row['status'], Status::REVERSED ) ) {
			$this->commissions->updateStatus( (int) $row['id'], Status::REVERSED, $note );
		}
	}

	/**
	 * @param array<string, mixed> $affiliate
	 */
	private function buildPolicy( array $affiliate ): CommissionPolicy {
		$rateBp = (int) $affiliate['rate_basis_points'];
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
			$max > 0 ? Money::of( $max ) : null,
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
