<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Infrastructure\Models;

use App\Modules\Invoicing\Domain\Exceptions\InvoiceException;
use App\Modules\Invoicing\Domain\InvoiceType;
use App\Modules\Invoicing\Infrastructure\Factories\InvoiceFactory;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Issued fiscal document. Immutable once created (cahier §6.7): updates and
 * deletes throw — a correction is a credit note plus a new invoice.
 *
 * @property int $id
 * @property string $number
 * @property InvoiceType $type
 * @property string $order_number
 * @property int|null $credited_invoice_id
 * @property string $customer_name
 * @property string $currency
 * @property int $vat_basis_points
 * @property int $net_amount
 * @property int $vat_amount
 * @property int $total_amount
 * @property int $discount_amount
 * @property string|null $coupon_code
 * @property string|null $reason
 * @property int|null $issued_by
 * @property Carbon $issued_at
 * @property-read Collection<int, InvoiceLine> $lines
 */
class Invoice extends Model
{
    /** @use HasFactory<InvoiceFactory> */
    use HasFactory;

    protected $fillable = [
        'number',
        'type',
        'order_number',
        'credited_invoice_id',
        'customer_name',
        'currency',
        'vat_basis_points',
        'net_amount',
        'vat_amount',
        'total_amount',
        'discount_amount',
        'coupon_code',
        'reason',
        'issued_by',
        'issued_at',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw InvoiceException::immutable());
        static::deleting(fn () => throw InvoiceException::immutable());
    }

    protected function casts(): array
    {
        return [
            'type' => InvoiceType::class,
            'issued_at' => 'datetime',
        ];
    }

    /**
     * @return HasMany<InvoiceLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(InvoiceLine::class);
    }

    /** Amount already cancelled by credit notes (full or partial). */
    public function creditedAmount(): int
    {
        return (int) self::query()->where('credited_invoice_id', $this->id)->sum('total_amount');
    }

    /** What a further credit note may still cancel; 0 for credit notes and fully credited invoices. */
    public function remainingCreditable(): int
    {
        return $this->type === InvoiceType::Invoice ? max(0, $this->total_amount - $this->creditedAmount()) : 0;
    }

    protected static function newFactory(): InvoiceFactory
    {
        return InvoiceFactory::new();
    }
}
