<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $goods_amount
 * @property int $landed_amount
 * @property string|null $notes
 * @property int|null $received_by
 * @property Carbon|null $created_at
 * @property-read Collection<int, GoodsReceiptLine> $lines
 */
class GoodsReceipt extends Model
{
    protected $fillable = ['purchase_order_id', 'goods_amount', 'landed_amount', 'notes', 'received_by'];

    /** @return HasMany<GoodsReceiptLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(GoodsReceiptLine::class);
    }
}
