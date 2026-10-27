<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Infrastructure\Models;

use App\Modules\Promotions\Domain\CouponType;
use App\Modules\Promotions\Infrastructure\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $code
 * @property CouponType $type
 * @property int $value
 * @property int $min_order_amount
 * @property int|null $max_uses
 * @property int|null $max_uses_per_customer
 * @property int $used_count
 * @property Carbon|null $starts_at
 * @property Carbon|null $ends_at
 * @property bool $active
 * @property int|null $created_by
 */
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory;

    protected $fillable = [
        'code',
        'type',
        'value',
        'min_order_amount',
        'max_uses',
        'max_uses_per_customer',
        'used_count',
        'starts_at',
        'ends_at',
        'active',
        'created_by',
    ];

    protected function casts(): array
    {
        return [
            'type' => CouponType::class,
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'active' => 'boolean',
        ];
    }

    /** Codes are case-insensitive for the customer, upper-case in the database. */
    public static function normalize(string $code): string
    {
        return strtoupper(trim($code));
    }

    protected static function newFactory(): CouponFactory
    {
        return CouponFactory::new();
    }
}
