<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Integration;

use WooAffiliatePro\Domain\Money;
use WooAffiliatePro\Domain\Payout\BalanceCalculator;
use WooAffiliatePro\Domain\Payout\PayoutRequest;
use WooAffiliatePro\Repository\AffiliateRepository;
use WooAffiliatePro\Repository\CommissionRepository;
use WooAffiliatePro\Repository\PayoutRepository;
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
			get_woocommerce_currency()
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

		$commissionRepo = new CommissionRepository();
		$payoutRepo     = new PayoutRepository();

		$balance = ( new BalanceCalculator() )->calculate(
			$commissionRepo->rowsForAffiliate( (int) $affiliate['id'] ),
			$payoutRepo->rowsForAffiliate( (int) $affiliate['id'] ),
			get_woocommerce_currency()
		);

		$amount  = Money::of( (int) $request->get_param( 'amount' ), get_woocommerce_currency() );
		$minimum = Money::of( Settings::int( 'min_payout' ), get_woocommerce_currency() );
		$payout  = new PayoutRequest( $amount, (string) $affiliate['payout_method'] );
		$errors  = $payout->validate( $balance, $minimum );

		if ( ! empty( $errors ) ) {
			return new \WP_REST_Response( array( 'errors' => $errors ), 422 );
		}

		$payoutRepo->create(
			array(
				'affiliate_id' => (int) $affiliate['id'],
				'amount'       => $amount->amount(),
				'method'       => (string) $affiliate['payout_method'],
			)
		);

		return new \WP_REST_Response( array( 'created' => true ), 201 );
	}

	public function affiliates(): \WP_REST_Response {
		$repository = new AffiliateRepository();
		$rows       = $repository->paginate( 1, 100 );

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
}
