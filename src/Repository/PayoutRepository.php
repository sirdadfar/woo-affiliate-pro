<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Repository;

/**
 * دسترسی به جدول درخواست‌های تسویه.
 */
final class PayoutRepository {

	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wap_payouts';
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function create( array $data ): int {
		global $wpdb;

		$wpdb->insert(
			$this->table(),
			array(
				'affiliate_id' => (int) $data['affiliate_id'],
				'amount'       => (int) $data['amount'],
				'method'       => (string) ( $data['method'] ?? 'card' ),
				'status'       => (string) ( $data['status'] ?? 'requested' ),
				'reference'    => (string) ( $data['reference'] ?? '' ),
				'note'         => (string) ( $data['note'] ?? '' ),
				'requested_at' => current_time( 'mysql', true ),
				'paid_at'      => null,
			),
			array( '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @return array<int, array{amount: int, status: string}>
	 */
	public function rowsForAffiliate( int $affiliateId ): array {
		global $wpdb;

		$rows = $wpdb->get_results(
			$wpdb->prepare(
				'SELECT amount, status FROM ' . $this->table() . ' WHERE affiliate_id = %d',
				$affiliateId
			),
			ARRAY_A
		);

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

	public function sumOpen( int $affiliateId ): int {
		global $wpdb;

		return (int) $wpdb->get_var(
			$wpdb->prepare(
				'SELECT COALESCE(SUM(amount), 0) FROM ' . $this->table() . " WHERE affiliate_id = %d AND status IN ('requested','processing')",
				$affiliateId
			)
		);
	}

	public function updateStatus( int $id, string $status, string $reference = '', string $note = '' ): bool {
		global $wpdb;

		$data   = array(
			'status'    => $status,
			'reference' => $reference,
			'note'      => $note,
		);
		$format = array( '%s', '%s', '%s' );

		if ( 'paid' === $status ) {
			$data['paid_at'] = current_time( 'mysql', true );
			$format[]        = '%s';
		}

		$result = $wpdb->update( $this->table(), $data, array( 'id' => $id ), $format, array( '%d' ) );

		return false !== $result;
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
