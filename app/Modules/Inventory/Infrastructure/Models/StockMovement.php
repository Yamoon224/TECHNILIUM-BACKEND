<?php

declare(strict_types=1);

namespace App\Modules\Inventory\Infrastructure\Models;

use App\Modules\Inventory\Infrastructure\Factories\StockMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $offer_id
 * @property int|null $created_by
 * @property int $quantity_delta
 * @property int $quantity_after
 * @property string|null $reason
 * @property Carbon|null $created_at
 */
class StockMovement extends Model
{
    /** @use HasFactory<StockMovementFactory> */
    use HasFactory;

    protected $fillable = [
        'offer_id',
        'created_by',
        'quantity_delta',
        'quantity_after',
        'reason',
    ];

    protected static function newFactory(): StockMovementFactory
    {
        return StockMovementFactory::new();
    }
}
