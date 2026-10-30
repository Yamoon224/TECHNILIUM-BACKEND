<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One step in an order's life: the (parent) status it reached, and when.
 * Append-only - written by OrderStatusLog, never edited.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $sub_order_id
 * @property string $status
 * @property \Illuminate\Support\Carbon $occurred_at
 */
class OrderStatusEvent extends Model
{
    protected $fillable = ['order_id', 'sub_order_id', 'status', 'occurred_at'];

    protected function casts(): array
    {
        return ['occurred_at' => 'datetime'];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }
}
