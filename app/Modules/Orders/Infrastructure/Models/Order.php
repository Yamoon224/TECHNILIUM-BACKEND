<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Models;

use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Factories\OrderFactory;
use App\Modules\Shared\ValueObjects\Currency;
use App\Modules\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_number
 * @property int|null $customer_id
 * @property string $customer_name
 * @property string $customer_phone
 * @property string $delivery_address
 * @property string|null $delivery_city
 * @property string|null $notes
 * @property string $payment_method
 * @property OrderStatus $status
 * @property int $subtotal_amount
 * @property string $currency
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 * @property-read Collection<int, OrderItem> $items
 * @property-read Collection<int, SubOrder> $subOrders
 */
class Order extends Model
{
    /** @use HasFactory<OrderFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'customer_id',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'delivery_city',
        'notes',
        'payment_method',
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
     * @return HasMany<OrderItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(OrderItem::class);
    }

    /**
     * @return HasMany<SubOrder, $this>
     */
    public function subOrders(): HasMany
    {
        return $this->hasMany(SubOrder::class);
    }

    public function subtotal(): Money
    {
        return Money::ofMinorUnits($this->subtotal_amount, Currency::from($this->currency));
    }

    protected static function newFactory(): OrderFactory
    {
        return OrderFactory::new();
    }
}
