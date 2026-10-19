<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Repository;

/**
 * دسترسی به جدول نمایندگان.
 *
 * تمام کوئری‌ها با prepare اجرا می‌شوند؛ هیچ رشتهٔ ورودی کاربر
 * مستقیماً در SQL قرار نمی‌گیرد.
 */
final class AffiliateRepository {

	public const STATUS_PENDING  = 'pending';
	public const STATUS_ACTIVE   = 'active';
	public const STATUS_REJECTED = 'rejected';
	public const STATUS_DISABLED = 'disabled';

	private function table(): string {
		global $wpdb;

		return $wpdb->prefix . 'wap_affiliates';
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
	public function findByCode( string $code ): ?array {
		global $wpdb;

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE code = %s', $code ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByUserId( int $userId ): ?array {
		global $wpdb;

		if ( $userId <= 0 ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE user_id = %d', $userId ),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * @return array<string, mixed>|null
	 */
	public function findByEmail( string $email ): ?array {
		global $wpdb;

		if ( '' === $email ) {
			return null;
		}

		$row = $wpdb->get_row(
			$wpdb->prepare( 'SELECT * FROM ' . $this->table() . ' WHERE email = %s', strtolower( $email ) ),
			ARRAY_A
		);

		return $row ?: null;
	}

	public function codeExists( string $code ): bool {
		return null !== $this->findByCode( $code );
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function create( array $data ): int {
		global $wpdb;

		$now = current_time( 'mysql', true );

		$defaults = array(
			'user_id'           => 0,
			'code'              => '',
			'name'              => '',
			'email'             => '',
			'mobile'            => '',
			'status'            => self::STATUS_PENDING,
			'rate_basis_points' => 1000,
			'payout_method'     => 'card',
			'payout_details'    => '',
			'created_at'        => $now,
			'updated_at'        => $now,
		);

		$row = array_merge( $defaults, $data );

		$wpdb->insert(
			$this->table(),
			array(
				'user_id'           => (int) $row['user_id'],
				'code'              => (string) $row['code'],
				'name'              => (string) $row['name'],
				'email'             => strtolower( (string) $row['email'] ),
				'mobile'            => (string) $row['mobile'],
				'status'            => (string) $row['status'],
				'rate_basis_points' => (int) $row['rate_basis_points'],
				'payout_method'     => (string) $row['payout_method'],
				'payout_details'    => (string) $row['payout_details'],
				'created_at'        => (string) $row['created_at'],
				'updated_at'        => (string) $row['updated_at'],
			),
			array( '%d', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s', '%s', '%s' )
		);

		return (int) $wpdb->insert_id;
	}

	/**
	 * @param array<string, mixed> $data
	 */
	public function update( int $id, array $data ): bool {
		global $wpdb;

		$allowed = array(
			'user_id'           => '%d',
			'code'              => '%s',
			'name'              => '%s',
			'email'             => '%s',
			'mobile'            => '%s',
			'status'            => '%s',
			'rate_basis_points' => '%d',
			'payout_method'     => '%s',
			'payout_details'    => '%s',
		);

		$set    = array();
		$format = array();

		foreach ( $allowed as $key => $placeholder ) {
			if ( array_key_exists( $key, $data ) ) {
				$set[ $key ] = '%d' === $placeholder ? (int) $data[ $key ] : (string) $data[ $key ];
				$format[]    = $placeholder;
			}
		}

		if ( empty( $set ) ) {
			return false;
		}

		$set['updated_at'] = current_time( 'mysql', true );
		$format[]          = '%s';

		$result = $wpdb->update( $this->table(), $set, array( 'id' => $id ), $format, array( '%d' ) );

		return false !== $result;
	}

	/**
	 * @return array<int, array<string, mixed>>
	 */
	public function paginate( int $page = 1, int $perPage = 20, string $status = '' ): array {
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

	public function count( string $status = '' ): int {
		global $wpdb;

		if ( '' !== $status ) {
			return (int) $wpdb->get_var(
				$wpdb->prepare( 'SELECT COUNT(*) FROM ' . $this->table() . ' WHERE status = %s', $status )
			);
		}

		return (int) $wpdb->get_var( 'SELECT COUNT(*) FROM ' . $this->table() );
	}
}
