<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration;

defined( 'ABSPATH' ) || exit;

use WooAffiliatePro\Domain\Money;
use WooAffiliatePro\Domain\Payout\BalanceCalculator;
use WooAffiliatePro\Domain\Payout\PayoutRequest;
use WooAffiliatePro\Repository\AffiliateRepository;
use WooAffiliatePro\Repository\CommissionRepository;
use WooAffiliatePro\Repository\PayoutRepository;
use WooAffiliatePro\Support\Lock;
use WooAffiliatePro\Support\RateLimiter;
use WooAffiliatePro\Support\Settings;

/**
 * API برای پنل‌های خارجی و اپ موبایل.
 *
 * همهٔ مسیرها زیر `wap/v1` هستند و هیچ‌کدام بدون احراز هویت کار نمی‌کنند.
 * برای نوشتن از nonce استفاده نمی‌شود چون REST API وردپرس خودش
 * با کوکی یا Application Password احراز هویت می‌کند.
 */
final class RestController {

	public const NAMESPACE_V1 = 'wap/v1';

	public function register(): void {
		add_action( 'rest_api_init', array( $this, 'routes' ) );
	}

	public function routes(): void {
		register_rest_route(
			self::NAMESPACE_V1,
			'/me',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'me' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/payouts',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'createPayout' ),
				'permission_callback' => static function () {
					return is_user_logged_in();
				},
				'args'                => array(
					'amount' => array(
						'required'          => true,
						'type'              => 'integer',
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE_V1,
			'/affiliates',
			array(
				'methods'             => 'GET',
				'callback'            => array( $this, 'affiliates' ),
				'permission_callback' => static function () {
					return current_user_can( 'manage_woocommerce' );
				},
				'args'                => array(
					'page'     => array(
						'type'              => 'integer',
						'default'           => 1,
						'minimum'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'type'              => 'integer',
						'default'           => 50,
						'minimum'           => 1,
						'maximum'           => 100,
						'sanitize_callback' => 'absint',
					),
				),
			)
		);
	}

	public function me(): \WP_REST_Response {
		$affiliates = new AffiliateRepository();
		$affiliate  = $affiliates->findByUserId( get_current_user_id() );

		if ( null === $affiliate ) {
			return new \WP_REST_Response(
				array(
					'registered' => false,
					'join_url'   => home_url( '/' ),
				),
				200
			);
		}

		$commissions = new CommissionRepository();
		$payouts     = new PayoutRepository();

		$balance = ( new BalanceCalculator() )->calculate(
			$commissions->rowsForAffiliate( (int) $affiliate['id'] ),
			$payouts->rowsForAffiliate( (int) $affiliate['id'] ),
			get_woocommerce_currency(),
			$this->priceDecimals()
		);

		return new \WP_REST_Response(
			array(
				'registered' => true,
				'code'       => (string) $affiliate['code'],
				'status'     => (string) $affiliate['status'],
				'ref_url'    => \WooAffiliatePro\Domain\Referral\CodeGenerator::toUrlParam( (string) $affiliate['code'], home_url( '/' ) ),
				'balance'    => $balance->toArray(),
			),
			200
		);
	}

	public function createPayout( \WP_REST_Request $request ): \WP_REST_Response {
		$affiliates = new AffiliateRepository();
		$affiliate  = $affiliates->findByUserId( get_current_user_id() );

		if ( null === $affiliate || AffiliateRepository::STATUS_ACTIVE !== $affiliate['status'] ) {
			return new \WP_REST_Response( array( 'error' => 'no_active_affiliate' ), 403 );
		}

		$affiliateId = (int) $affiliate['id'];
		$decimals    = $this->priceDecimals();

		// همان لایهٔ محافظت مسیر وب: محدودیت نرخ + قفل اتمیک، تا اپ موبایل هم
		// نتواند با درخواست پشت‌سرهم چند تسویهٔ تکراری بسازد.
		if ( ! RateLimiter::allow( 'rest_payout:' . $affiliateId, 10, MINUTE_IN_SECONDS ) ) {
			return new \WP_REST_Response( array( 'error' => 'rate_limited' ), 429 );
		}

		if ( ! Lock::acquire( 'rest_payout:' . $affiliateId, 30 ) ) {
			return new \WP_REST_Response( array( 'error' => 'duplicate_request' ), 409 );
		}

		$commissionRepo = new CommissionRepository();
		$payoutRepo     = new PayoutRepository();

		$balance = ( new BalanceCalculator() )->calculate(
			$commissionRepo->rowsForAffiliate( $affiliateId ),
			$payoutRepo->rowsForAffiliate( $affiliateId ),
			get_woocommerce_currency(),
			$decimals
		);

		$amount  = Money::of( (int) $request->get_param( 'amount' ), get_woocommerce_currency(), $decimals );
		$minimum = Money::of( max( 0, Settings::int( 'min_payout' ) ), get_woocommerce_currency(), $decimals );
		$payout  = new PayoutRequest( $amount, (string) $affiliate['payout_method'] );
		$errors  = $payout->validate( $balance, $minimum );

		if ( ! empty( $errors ) ) {
			Lock::release( 'rest_payout:' . $affiliateId );

			return new \WP_REST_Response( array( 'errors' => $errors ), 422 );
		}

		$created = $payoutRepo->create(
			array(
				'affiliate_id' => $affiliateId,
				'amount'       => $amount->amount(),
				'method'       => (string) $affiliate['payout_method'],
			)
		);

		if ( $created <= 0 ) {
			Lock::release( 'rest_payout:' . $affiliateId );

			return new \WP_REST_Response( array( 'error' => 'payout_not_created' ), 500 );
		}

		return new \WP_REST_Response( array( 'created' => true ), 201 );
	}

	public function affiliates( \WP_REST_Request $request ): \WP_REST_Response {
		$repository = new AffiliateRepository();
		$rows       = $repository->paginate(
			max( 1, (int) $request->get_param( 'page' ) ),
			max( 1, min( 100, (int) $request->get_param( 'per_page' ) ) )
		);

		$data = array_map(
			static function ( $row ) {
				return array(
					'id'     => (int) $row['id'],
					'code'   => (string) $row['code'],
					'name'   => (string) $row['name'],
					'email'  => (string) $row['email'],
					'status' => (string) $row['status'],
					'rate'   => (int) $row['rate_basis_points'] / 100,
				);
			},
			$rows
		);

		return new \WP_REST_Response( $data, 200 );
	}

	private function priceDecimals(): int {
		$decimals = function_exists( 'wc_get_price_decimals' ) ? (int) wc_get_price_decimals() : 0;

		return max( 0, min( 4, $decimals ) );
	}
}
