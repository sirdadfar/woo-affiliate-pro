<?php
declare( strict_types=1 );

namespace WooAffiliatePro\Domain\Commission;

use WooAffiliatePro\Domain\Money;

/**
 * سیاست پورسانت: درصدی یا مبلغ ثابت، با استثناها و سقف.
 *
 * مقدار درصد همیشه به «پایهٔ یک‌صدم درصد» نگه داشته می‌شود
 * (۱۰٪ = ۱۰۰۰) تا محاسبه با عدد صحیح انجام شود.
 */
final class CommissionPolicy {

	public const TYPE_PERCENT = 'percent';
	public const TYPE_FIXED   = 'fixed';

	/** @var string */
	private $rateType;

	/** @var int basis points */
	private $rateBasisPoints;

	/** @var Money|null فقط برای نوع fixed */
	private $fixedAmount;

	/** @var bool */
	private $includeShipping;

	/** @var bool */
	private $includeTax;

	/** @var array<int, array{type: string, value: int}> */
	private $categoryRates;

	/** @var int[] */
	private $excludeProductIds;

	/** @var Money|null */
	private $maxCommission;

	/** @var int[] */
	private $excludeCategoryIds;

	/**
	 * @param array<int, array{type: string, value: int}> $categoryRates
	 * @param int[]                                        $excludeProductIds
	 * @param int[]                                        $excludeCategoryIds
	 */
	public function __construct(
		string $rateType,
		int $rateBasisPoints,
		?Money $fixedAmount = null,
		bool $includeShipping = false,
		bool $includeTax = false,
		array $categoryRates = array(),
		array $excludeProductIds = array(),
		?Money $maxCommission = null,
		array $excludeCategoryIds = array()
	) {
		if ( ! in_array( $rateType, array( self::TYPE_PERCENT, self::TYPE_FIXED ), true ) ) {
			throw new \InvalidArgumentException( sprintf( 'نوع نرخ نامعتبر است: %s', $rateType ) );
		}
		if ( self::TYPE_PERCENT === $rateType && ( $rateBasisPoints < 0 || $rateBasisPoints > 10000 ) ) {
			throw new \InvalidArgumentException( 'درصد باید بین ۰ تا ۱۰۰ باشد.' );
		}
		if ( self::TYPE_FIXED === $rateType && null === $fixedAmount ) {
			throw new \InvalidArgumentException( 'برای نرخ ثابت، مبلغ ثابت لازم است.' );
		}
		if ( null !== $fixedAmount && $fixedAmount->isNegative() ) {
			throw new \InvalidArgumentException( 'مبلغ ثابت نمی‌تواند منفی باشد.' );
		}

		$this->rateType           = $rateType;
		$this->rateBasisPoints    = $rateBasisPoints;
		$this->fixedAmount        = $fixedAmount;
		$this->includeShipping    = $includeShipping;
		$this->includeTax         = $includeTax;
		$this->categoryRates      = $categoryRates;
		$this->excludeProductIds  = array_map( 'intval', $excludeProductIds );
		$this->maxCommission      = $maxCommission;
		$this->excludeCategoryIds = array_map( 'intval', $excludeCategoryIds );
	}

	public static function percent( int $basisPoints, string $currency = 'IRR', int $decimals = 0 ): self {
		return new self( self::TYPE_PERCENT, $basisPoints, null, false, false, array(), array(), null );
	}

	public static function fixed( Money $amount ): self {
		return new self( self::TYPE_FIXED, 0, $amount, false, false, array(), array(), null );
	}

	public function rateType(): string {
		return $this->rateType;
	}

	public function rateBasisPoints(): int {
		return $this->rateBasisPoints;
	}

	public function fixedAmount(): ?Money {
		return $this->fixedAmount;
	}

	public function includesShipping(): bool {
		return $this->includeShipping;
	}

	public function includesTax(): bool {
		return $this->includeTax;
	}

	/**
	 * @return array<int, array{type: string, value: int}>
	 */
	public function categoryRates(): array {
		return $this->categoryRates;
	}

	/**
	 * @return int[]
	 */
	public function excludeProductIds(): array {
		return $this->excludeProductIds;
	}

	/**
	 * @return int[]
	 */
	public function excludeCategoryIds(): array {
		return $this->excludeCategoryIds;
	}

	public function maxCommission(): ?Money {
		return $this->maxCommission;
	}

	public function isPercent(): bool {
		return self::TYPE_PERCENT === $this->rateType;
	}

	/**
	 * نسخهٔ تغییر‌یافته با نرخ دسته‌بندی جدید — برای تست و پیکربندی زنجیره‌ای.
	 *
	 * @param array<int, array{type: string, value: int}> $rates
	 */
	public function withCategoryRates( array $rates ): self {
		return new self(
			$this->rateType,
			$this->rateBasisPoints,
			$this->fixedAmount,
			$this->includeShipping,
			$this->includeTax,
			$rates,
			$this->excludeProductIds,
			$this->maxCommission,
			$this->excludeCategoryIds
		);
	}

	public function withMaxCommission( ?Money $max ): self {
		return new self(
			$this->rateType,
			$this->rateBasisPoints,
			$this->fixedAmount,
			$this->includeShipping,
			$this->includeTax,
			$this->categoryRates,
			$this->excludeProductIds,
			$max,
			$this->excludeCategoryIds
		);
	}
}
