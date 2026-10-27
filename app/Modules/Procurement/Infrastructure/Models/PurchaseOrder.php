<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Infrastructure\Models;

use App\Modules\Procurement\Domain\PurchaseOrderStatus;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $supplier_id
 * @property PurchaseOrderStatus $status
 * @property string $currency
 * @property Carbon|null $expected_at
 * @property Carbon|null $ordered_at
 * @property string|null $notes
 * @property int|null $created_by
 * @property Carbon|null $created_at
 * @property-read Supplier $supplier
 * @property-read Collection<int, PurchaseOrderLine> $lines
 * @property-read Collection<int, LandedCost> $landedCosts
 */
class PurchaseOrder extends Model
{
    protected $fillable = ['supplier_id', 'status', 'currency', 'expected_at', 'ordered_at', 'notes', 'created_by'];

    protected function casts(): array
    {
        return [
            'status' => PurchaseOrderStatus::class,
            'expected_at' => 'date',
            'ordered_at' => 'datetime',
        ];
    }

    public function number(): string
    {
        return 'BC-'.sprintf('%06d', $this->id);
    }

    /** Value of everything ordered: the base landed costs are spread over. */
    public function goodsTotal(): int
    {
        return (int) $this->lines->sum(fn (PurchaseOrderLine $line) => $line->quantity_ordered * $line->unit_cost_amount);
    }

    public function landedTotal(): int
    {
        return (int) $this->landedCosts->sum('amount');
    }

    /** @return BelongsTo<Supplier, $this> */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /** @return HasMany<PurchaseOrderLine, $this> */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('id');
    }

    /** @return HasMany<GoodsReceipt, $this> */
    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class)->orderBy('id');
    }

    /** @return HasMany<LandedCost, $this> */
    public function landedCosts(): HasMany
    {
        return $this->hasMany(LandedCost::class)->orderBy('id');
    }
}
