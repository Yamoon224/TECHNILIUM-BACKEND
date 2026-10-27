<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use App\Modules\Procurement\Infrastructure\Factories\SupplierInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property int|null $purchase_order_id
 * @property string $number
 * @property string $kind goods|landed_cost
 * @property int $amount
 * @property int $paid_amount
 * @property Carbon $invoice_date
 * @property Carbon $due_date
 * @property-read Supplier $supplier
 */
class SupplierInvoice extends Model
{
    /** @use HasFactory<SupplierInvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'supplier_id', 'purchase_order_id', 'number', 'kind', 'amount', 'paid_amount', 'invoice_date', 'due_date',
    ];

    protected function casts(): array
    {
        return ['invoice_date' => 'date', 'due_date' => 'date'];
    }

    public function outstanding(): int
    {
        return max(0, $this->amount - $this->paid_amount);
    }

    public function isSettled(): bool
    {
        return $this->outstanding() === 0;
    }

    /** Past its due date and still owing something. */
    public function isOverdue(): bool
    {
        return ! $this->isSettled() && $this->due_date->endOfDay()->isPast();
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    protected static function newFactory(): SupplierInvoiceFactory
    {
        return SupplierInvoiceFactory::new();
    }
}
