<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $supplier_invoice_id
 * @property int $amount
 * @property string $method
 * @property string|null $reference
 * @property int|null $paid_by
 */
class SupplierPayment extends Model
{
    protected $fillable = ['supplier_invoice_id', 'amount', 'method', 'reference', 'paid_by'];
}
