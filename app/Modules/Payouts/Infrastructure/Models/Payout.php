<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Infrastructure\Models;

use App\Modules\Payouts\Domain\PayoutStatus;
use App\Modules\Payouts\Infrastructure\Factories\PayoutFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $vendor_id
 * @property string $vendor_name
 * @property PayoutStatus $status
 * @property string $currency
 * @property int $commission_basis_points
 * @property int $gross_amount
 * @property int $commission_amount
 * @property int $net_amount
 * @property string|null $payment_reference
 * @property int|null $created_by
 * @property Carbon|null $paid_at
 * @property Carbon|null $created_at
 * @property-read Collection<int, PayoutItem> $items
 */
class Payout extends Model
{
    /** @use HasFactory<PayoutFactory> */
    use HasFactory;

    protected $fillable = [
        'vendor_id',
        'vendor_name',
        'status',
        'currency',
        'commission_basis_points',
        'gross_amount',
        'commission_amount',
        'net_amount',
        'payment_reference',
        'created_by',
        'paid_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => PayoutStatus::class,
            'paid_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<PayoutItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(PayoutItem::class);
    }

    protected static function newFactory(): PayoutFactory
    {
        return PayoutFactory::new();
    }
}
