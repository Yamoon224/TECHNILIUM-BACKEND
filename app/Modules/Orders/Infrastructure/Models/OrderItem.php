<?php

declare(strict_types=1);

namespace App\Modules\Orders\Infrastructure\Models;

use App\Modules\Orders\Infrastructure\Factories\OrderItemFactory;
use App\Modules\Shared\ValueObjects\Currency;
use App\Modules\Shared\ValueObjects\Money;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $order_id
 * @property int $offer_id
 * @property string $product_name
 * @property string $brand
 * @property int $unit_price_amount
 * @property string $currency
 * @property int $quantity
 * @property int $line_total_amount
 */
class OrderItem extends Model
{
    /** @use HasFactory<OrderItemFactory> */
    use HasFactory;

    protected $fillable = [
        'order_id',
        'offer_id',
        'product_name',
        'brand',
        'unit_price_amount',
        'currency',
        'quantity',
        'line_total_amount',
    ];

    /**
     * @return BelongsTo<Order, $this>
     */
    public function order(): BelongsTo
    {
        return $this->belongsTo(Order::class);
    }

    public function unitPrice(): Money
    {
        return Money::ofMinorUnits($this->unit_price_amount, Currency::from($this->currency));
    }

    public function lineTotal(): Money
    {
        return Money::ofMinorUnits($this->line_total_amount, Currency::from($this->currency));
    }

    protected static function newFactory(): OrderItemFactory
    {
        return OrderItemFactory::new();
    }
}
