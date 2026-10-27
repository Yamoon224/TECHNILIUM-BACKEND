<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Models;

use App\Modules\Accounting\Domain\Exceptions\AccountingException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * Append-only ledger entry (cahier §10.2): never updated or deleted.
 *
 * @property int $id
 * @property string $journal
 * @property Carbon $entry_date
 * @property string $description
 * @property string $source_type
 * @property int $source_id
 * @property string|null $reference
 * @property string $currency
 * @property-read Collection<int, JournalEntryLine> $lines
 */
class JournalEntry extends Model
{
    protected $fillable = [
        'journal',
        'entry_date',
        'description',
        'source_type',
        'source_id',
        'reference',
        'currency',
    ];

    protected static function booted(): void
    {
        static::updating(fn () => throw AccountingException::immutable());
        static::deleting(fn () => throw AccountingException::immutable());
    }

    protected function casts(): array
    {
        return ['entry_date' => 'date'];
    }

    /**
     * @return HasMany<JournalEntryLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(JournalEntryLine::class);
    }
}
