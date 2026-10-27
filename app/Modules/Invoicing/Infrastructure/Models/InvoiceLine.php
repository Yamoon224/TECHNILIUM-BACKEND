<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $invoice_id
 * @property string $description
 * @property int $quantity
 * @property int $unit_price_amount
 * @property int $line_total_amount
 */
class InvoiceLine extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'invoice_id',
        'description',
        'quantity',
        'unit_price_amount',
        'line_total_amount',
    ];
}
