<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Factories\OfferFactory;
use App\Modules\Shared\ValueObjects\Currency;
use App\Modules\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $product_variant_id
 * @property int|null $vendor_id
 * @property int $price_amount
 * @property string $currency
 * @property int|null $compare_at_price_amount
 * @property string|null $condition_grade
 * @property int $warranty_months
 * @property OfferStatus $status
 * @property Carbon|null $published_at
 */
class Offer extends Model
{
    /** @use HasFactory<OfferFactory> */
    use HasFactory;

    protected $fillable = [
        'product_variant_id',
        'vendor_id',
        'price_amount',
        'currency',
        'compare_at_price_amount',
        'condition_grade',
        'warranty_months',
        'status',
        'published_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OfferStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ProductVariant, $this>
     */
    public function variant(): BelongsTo
    {
        return $this->belongsTo(ProductVariant::class, 'product_variant_id');
    }

    public function isSoldByTechnilium(): bool
    {
        return $this->vendor_id === null;
    }

    public function price(): Money
    {
        return Money::ofMinorUnits($this->price_amount, Currency::from($this->currency));
    }

    public function compareAtPrice(): ?Money
    {
        return $this->compare_at_price_amount === null
            ? null
            : Money::ofMinorUnits($this->compare_at_price_amount, Currency::from($this->currency));
    }

    protected static function newFactory(): OfferFactory
    {
        return OfferFactory::new();
    }
}
