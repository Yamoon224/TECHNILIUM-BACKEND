<?php

declare(strict_types=1);

namespace App\Modules\Sales\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_number
 * @property int $seller_id
 * @property int $payment_id
 * @property string $method
 * @property int $amount
 * @property string $currency
 * @property int|null $closing_id
 * @property Carbon|null $cancelled_at
 * @property int|null $cancelled_by
 * @property string|null $cancel_reason
 * @property int|null $cancel_closing_id
 * @property Carbon|null $created_at
 */
class InternalSale extends Model
{
    protected $fillable = [
        'order_number', 'seller_id', 'payment_id', 'method', 'amount', 'currency',
        'closing_id', 'cancelled_at', 'cancelled_by', 'cancel_reason', 'cancel_closing_id',
    ];

    protected function casts(): array
    {
        return ['cancelled_at' => 'datetime'];
    }
}
