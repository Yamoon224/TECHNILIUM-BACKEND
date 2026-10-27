<?php

declare(strict_types=1);

namespace App\Modules\Returns\Infrastructure\Models;

use App\Modules\Returns\Domain\ReturnStatus;
use App\Modules\Returns\Infrastructure\Factories\ReturnRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * "Return" is a reserved word in PHP, hence ReturnRequest (an RMA, cahier §6.11).
 *
 * @property int $id
 * @property string $order_number
 * @property ReturnStatus $status
 * @property string $reason
 * @property string|null $decision_note
 * @property string $currency
 * @property int $requested_amount
 * @property int|null $refunded_amount
 * @property list<array{offer_id: int, quantity: int}>|null $items
 * @property bool $restocked
 * @property int|null $created_by
 * @property Carbon|null $refunded_at
 * @property Carbon|null $created_at
 */
class ReturnRequest extends Model
{
    /** @use HasFactory<ReturnRequestFactory> */
    use HasFactory;

    protected $table = 'return_requests';

    protected $fillable = [
        'order_number',
        'status',
        'reason',
        'decision_note',
        'currency',
        'requested_amount',
        'refunded_amount',
        'items',
        'restocked',
        'created_by',
        'refunded_at',
    ];

    protected function casts(): array
    {
        return [
            'status' => ReturnStatus::class,
            'refunded_at' => 'datetime',
            'items' => 'array',
            'restocked' => 'boolean',
        ];
    }

    public function number(): string
    {
        return 'RMA-'.sprintf('%06d', $this->id);
    }

    protected static function newFactory(): ReturnRequestFactory
    {
        return ReturnRequestFactory::new();
    }
}
