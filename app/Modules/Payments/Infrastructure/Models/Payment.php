<?php

declare(strict_types=1);

namespace App\Modules\Payments\Infrastructure\Models;

use App\Modules\Payments\Domain\PaymentMethod;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Factories\PaymentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_number
 * @property PaymentMethod $method
 * @property PaymentStatus $status
 * @property int $amount_amount
 * @property int $refunded_amount
 * @property string $currency
 * @property string|null $provider
 * @property string|null $provider_reference
 * @property int|null $created_by
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 */
class Payment extends Model
{
    /** @use HasFactory<PaymentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'method',
        'status',
        'amount_amount',
        'refunded_amount',
        'currency',
        'provider',
        'provider_reference',
        'created_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'method' => PaymentMethod::class,
            'status' => PaymentStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /** What can still be given back: collected amount minus refunds so far. */
    public function refundable(): int
    {
        return max(0, $this->amount_amount - $this->refunded_amount);
    }

    protected static function newFactory(): PaymentFactory
    {
        return PaymentFactory::new();
    }
}
