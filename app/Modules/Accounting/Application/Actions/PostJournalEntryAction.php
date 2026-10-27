<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Actions;

use App\Modules\Accounting\Domain\EntryLine;
use App\Modules\Accounting\Domain\Exceptions\AccountingException;
use App\Modules\Accounting\Infrastructure\Models\JournalEntry;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for writing to the ledger. Refuses unbalanced entries
 * and is idempotent per (source_type, source_id).
 */
final class PostJournalEntryAction
{
    /**
     * @param  list<EntryLine>  $lines
     */
    public function execute(
        string $journal,
        string $sourceType,
        int $sourceId,
        string $description,
        array $lines,
        string $currency,
        ?string $reference = null,
    ): JournalEntry {
        $debit = array_sum(array_map(fn (EntryLine $l) => $l->debit, $lines));
        $credit = array_sum(array_map(fn (EntryLine $l) => $l->credit, $lines));

        if ($debit !== $credit || $debit === 0) {
            throw AccountingException::unbalanced($debit, $credit);
        }

        $existing = JournalEntry::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->first();

        if ($existing !== null) {
            return $existing;
        }

        try {
            return DB::transaction(function () use ($journal, $sourceType, $sourceId, $description, $lines, $currency, $reference) {
                $entry = JournalEntry::query()->create([
                    'journal' => $journal,
                    'entry_date' => now()->toDateString(),
                    'description' => $description,
                    'source_type' => $sourceType,
                    'source_id' => $sourceId,
                    'reference' => $reference,
                    'currency' => $currency,
                ]);

                foreach ($lines as $line) {
                    $entry->lines()->create([
                        'account_code' => $line->account,
                        'debit' => $line->debit,
                        'credit' => $line->credit,
                    ]);
                }

                return $entry;
            });
        } catch (UniqueConstraintViolationException) {
            // Lost a race with a concurrent replay of the same event.
            return JournalEntry::query()->where('source_type', $sourceType)->where('source_id', $sourceId)->firstOrFail();
        }
    }
}
