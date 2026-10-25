<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Infrastructure\Factories\ProductVariantFactory;
use App\Modules\Shared\ValueObjects\Sku;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $product_id
 * @property string $sku
 * @property string|null $ean
 * @property array<string, mixed>|null $attributes
 * @property-read Collection<int, Offer> $offers
 */
class ProductVariant extends Model
{
    /** @use HasFactory<ProductVariantFactory> */
    use HasFactory;

    protected $fillable = [
        'product_id',
        'sku',
        'ean',
        'attributes',
    ];

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
        ];
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }

    /**
     * @return HasMany<Offer, $this>
     */
    public function offers(): HasMany
    {
        return $this->hasMany(Offer::class);
    }

    public function skuValueObject(): Sku
    {
        return Sku::fromString($this->sku);
    }

    protected static function newFactory(): ProductVariantFactory
    {
        return ProductVariantFactory::new();
    }
}
