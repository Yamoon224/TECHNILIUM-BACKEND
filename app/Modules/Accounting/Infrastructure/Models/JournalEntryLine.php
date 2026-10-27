<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Infrastructure\Models;

use App\Modules\Accounting\Domain\Exceptions\AccountingException;
use Illuminate\Database\Eloquent\Model;

/**
 * @property int $id
 * @property int $journal_entry_id
 * @property string $account_code
 * @property int $debit
 * @property int $credit
 */
class JournalEntryLine extends Model
{
    public $timestamps = false;

    protected $fillable = ['journal_entry_id', 'account_code', 'debit', 'credit'];

    protected static function booted(): void
    {
        static::updating(fn () => throw AccountingException::immutable());
        static::deleting(fn () => throw AccountingException::immutable());
    }
}
