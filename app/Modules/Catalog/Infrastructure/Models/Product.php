<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use App\Modules\Catalog\Domain\ConditionState;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Infrastructure\Factories\ProductFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * @property int $id
 * @property int $category_id
 * @property string $brand
 * @property string $name
 * @property string $slug
 * @property string|null $description
 * @property string|null $name_en
 * @property string|null $description_en
 * @property array<string, mixed>|null $attributes
 * @property ConditionState $condition_state
 * @property ProductStatus $status
 * @property-read Category $category
 * @property-read Collection<int, ProductVariant> $variants
 */
class Product extends Model
{
    /** @use HasFactory<ProductFactory> */
    use HasFactory;

    protected $fillable = [
        'category_id',
        'brand',
        'name',
        'slug',
        'description',
        'name_en',
        'description_en',
        'attributes',
        'condition_state',
        'status',
    ];

    /** Public URL of the cover photo (first of the gallery), or null when there is none. */
    public function imageUrl(): ?string
    {
        return $this->images->first()?->url();
    }

    /**
     * Photos in display order; position 0 is the cover.
     *
     * @return HasMany<ProductImage, $this>
     */
    public function images(): HasMany
    {
        return $this->hasMany(ProductImage::class)->orderBy('position')->orderBy('id');
    }

    protected function casts(): array
    {
        return [
            'attributes' => 'array',
            'condition_state' => ConditionState::class,
            'status' => ProductStatus::class,
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return HasMany<ProductVariant, $this>
     */
    public function variants(): HasMany
    {
        return $this->hasMany(ProductVariant::class);
    }

    protected static function newFactory(): ProductFactory
    {
        return ProductFactory::new();
    }
}
