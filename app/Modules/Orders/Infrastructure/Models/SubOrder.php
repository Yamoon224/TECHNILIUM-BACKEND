<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Models;

use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Factories\SubOrderFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * One seller's share of an order, with its own status and delivery date
 * (cahier §3 "sous-commandes"). vendor_id null = Technilium's own stock.
 *
 * @property int $id
 * @property int $order_id
 * @property int|null $vendor_id
 * @property OrderStatus $status
 * @property int $subtotal_amount
 * @property string $currency
 * @property Carbon|null $delivered_at
 * @property-read Order $order
 * @property-read Collection<int, OrderItem> $items
 */
class SubOrder extends Model
{
    /** @use HasFactory<SubOrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'vendor_id',
        'status',
        'subtotal_amount',
        'currency',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => OrderStatus::class,
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    /**
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    protected static function newFactory(): SubOrderFactory
    {
        return SubOrderFactory::new();
    }
}
