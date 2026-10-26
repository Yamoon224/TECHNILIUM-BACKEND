<?php

declare(strict_types=1);

namespace App\Modules\Shipping\Infrastructure\Models;

use App\Modules\Shipping\Domain\ShipmentStatus;
use App\Modules\Shipping\Infrastructure\Factories\ShipmentFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property string $order_number
 * @property int|null $assigned_to
 * @property string $customer_name
 * @property string $customer_phone
 * @property string $delivery_address
 * @property string|null $delivery_city
 * @property ShipmentStatus $status
 * @property int $attempts
 * @property string|null $notes
 * @property Carbon|null $delivered_at
 * @property Carbon|null $created_at
 */
class Shipment extends Model
{
    /** @use HasFactory<ShipmentFactory> */
    use HasFactory;

    protected $fillable = [
        'order_number',
        'assigned_to',
        'customer_name',
        'customer_phone',
        'delivery_address',
        'delivery_city',
        'status',
        'attempts',
        'notes',
        'delivered_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ShipmentStatus::class,
            'delivered_at' => 'datetime',
        ];
    }

    protected static function newFactory(): ShipmentFactory
    {
        return ShipmentFactory::new();
    }
}
