<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * One photo of a product. Position 0 is the cover shown on cards.
 *
 * @property int $id
 * @property int $product_id
 * @property string $path
 * @property int $position
 */
class ProductImage extends Model
{
    protected $fillable = ['product_id', 'path', 'position'];

    public function url(): string
    {
        return Storage::disk('public')->url($this->path);
    }

    /**
     * @return BelongsTo<Product, $this>
     */
    public function product(): BelongsTo
    {
        return $this->belongsTo(Product::class);
    }
}
