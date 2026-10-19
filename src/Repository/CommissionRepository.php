<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Repository;

use WooAffiliatePro\Domain\Commission\Status;

/**
 * دسترسی به جدول پورسانت‌ها.
 */
final class CommissionRepository {

	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wap_commissions';
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function find( int $id ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE id = %d', $id ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByOrder( int $orderId ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE order_id = %d', $orderId ),
			ARRAY_A
		);

		return $row ?: null;
	}

	public function existsForOrder( int $orderId ): bool {
		return null !== $this->findByOrder( $orderId );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function create( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$wpdb->insert(
			$this->table(),
			array(
				'affiliate_id'      => (int) $data['affiliate_id'],
				'order_id'          => (int) $data['order_id'],
				'amount'            => (int) $data['amount'],
				'base_amount'       => (int) ( $data['base_amount'] ?? 0 ),
				'rate_basis_points' => (int) ( $data['rate_basis_points'] ?? 0 ),
				'currency'          => (string) ( $data['currency'] ?? 'IRR' ),
				'status'            => (string) ( $data['status'] ?? Status::PENDING ),
				'note'              => (string) ( $data['note'] ?? '' ),
				'created_at'        => $now,
				'updated_at'        => $now,
			),
			array( '%d', '%d', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	public function updateStatus( int $id, string $status, string $note = '' ): bool {
		global $wpdb;

		$result = $wpdb->update(
			$this->table(),
			array(
				'status'     => $status,
				'note'       => $note,
				'updated_at' => current_time( 'mysql', true ),
			),
			array( 'id' => $id ),
			array( '%s', '%s', '%s' ),
			array( '%d' )
		);

		return false !== $result;
	}

	/**
	 * @param string[] $statuses
	 */
	public function sumForAffiliate( int $affiliateId, array $statuses ): int {
		global $wpdb;

		if ( empty( $statuses ) ) {
			return 0;
		}

		$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
		$params       = array_merge( array( $affiliateId ), array_values( $statuses ) );

		$sum = $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount), 0) FROM ' . $this->table() . " WHERE affiliate_id = %d AND status IN ({$placeholders})",
				$params
			)
		);

		return (int) $sum;
	}

	/**
	 * @param string[] $statuses
	 *
	 * @return array<int, array{amount: int, status: string}>
	 */
	public function rowsForAffiliate( int $affiliateId, array $statuses = array() ): array {
		global $wpdb;

		$sql = 'SELECT amount, status FROM ' . $this->table() . ' WHERE affiliate_id = %d';

		if ( ! empty( $statuses ) ) {
			$placeholders = implode( ',', array_fill( 0, count( $statuses ), '%s' ) );
			$sql         .= " AND status IN ({$placeholders})";
			$params       = array_merge( array( $affiliateId ), array_values( $statuses ) );
			$rows         = $wpdb->get_results( $wpdb->prepare( $sql, $params ), ARRAY_A );
		} else {
			$rows = $wpdb->get_results( $wpdb->prepare( $sql, $affiliateId ), ARRAY_A );
		}

		return array_map(
			static function ( $row ) {
				return array(
					'amount' => (int) $row['amount'],
					'status' => (string) $row['status'],
				);
			},
			is_array( $rows ) ? $rows : array()
		) ?: array();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function paginate( int $page = 1, int $perPage = 30, string $status = '' ): array {
		global $wpdb;

		$offset = max( 0, ( $page - 1 ) * $perPage );

		if ( '' !== $status ) {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . $this->table() . ' WHERE status = %s ORDER BY id DESC LIMIT %d OFFSET %d',
					$status,
					$perPage,
					$offset
				),
				ARRAY_A
			);
		} else {
			$rows = $wpdb->get_results(
				$wpdb->prepare(
					'SELECT * FROM ' . $this->table() . ' ORDER BY id DESC LIMIT %d OFFSET %d',
					$perPage,
					$offset
				),
				ARRAY_A
			);
		}

		return is_array( $rows ) ? $rows : array();
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function forAffiliate( int $affiliateId, int $limit = 20 ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT * FROM ' . $this->table() . ' WHERE affiliate_id = %d ORDER BY id DESC LIMIT %d',
				$affiliateId,
				$limit
			),
			ARRAY_A
		);

		return is_array( $rows ) ? $rows : array();
	}
}
