<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $purchase_order_id
 * @property int $offer_id
 * @property string $description
 * @property int $quantity_ordered
 * @property int $quantity_received
 * @property int $quantity_rejected
 * @property int $unit_cost_amount
 */
class PurchaseOrderLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'purchase_order_id', 'offer_id', 'description',
        'quantity_ordered', 'quantity_received', 'quantity_rejected', 'unit_cost_amount',
    ];

    /** Units ordered that are neither in stock nor refused yet. */
    public function outstanding(): int
    {
        return max(0, $this->quantity_ordered - $this->quantity_received - $this->quantity_rejected);
    }
}
