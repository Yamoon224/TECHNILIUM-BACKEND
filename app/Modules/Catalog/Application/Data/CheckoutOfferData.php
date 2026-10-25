<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Data;

use App\Modules\Shared\ValueObjects\Currency;
use App\Modules\Shared\ValueObjects\Money;

/**
 * Catalog's public, read-only view of an offer for checkout purposes —
 * the only shape other modules (Orders) are allowed to depend on. Never
 * expose Infrastructure\Models\Offer outside this module (cahier §9.1).
 */
final readonly class CheckoutOfferData
{
    public function __construct(
        public int $offerId,
        public string $productName,
        public string $brand,
        public int $priceAmount,
        public Currency $currency,
        public int $stockQuantity,
    ) {}

    public function price(): Money
    {
        return Money::ofMinorUnits($this->priceAmount, $this->currency);
    }
}
