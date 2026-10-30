<?php

declare(strict_types=1);

namespace App\Modules\Accounting\Application\Dashboard;

use App\Modules\Accounting\Infrastructure\Models\JournalEntry;
use App\Modules\Accounting\Infrastructure\Models\JournalEntryLine;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

/**
 * Dashboard block of the accounting feature: ledger activity of the period,
 * whether debits equal credits, entries per journal and the latest entries.
 */
final class AccountingSection implements DashboardSection
{
    public function key(): string
    {
        return 'accounting';
    }

    public function ability(): string
    {
        return 'manage-accounting';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $range = [$from->toDateString(), $to->toDateString()];

        $entries = JournalEntry::query()->whereDate('entry_date', '>=', $range[0])->whereDate('entry_date', '<=', $range[1])->count();

        $totals = JournalEntryLine::query()
            ->join('journal_entries', 'journal_entries.id', '=', 'journal_entry_lines.journal_entry_id')
            ->whereDate('journal_entries.entry_date', '>=', $range[0])
            ->whereDate('journal_entries.entry_date', '<=', $range[1])
            ->selectRaw('coalesce(sum(journal_entry_lines.debit), 0) as debit, coalesce(sum(journal_entry_lines.credit), 0) as credit')
            ->toBase()
            ->first();

        $debit = (int) ($totals->debit ?? 0);
        $credit = (int) ($totals->credit ?? 0);
        $balanced = $debit === $credit;
        // Share of the larger side that the smaller one covers: 100 % when the books balance.
        $balance = $balanced ? 100.0 : round(min($debit, $credit) * 100 / max($debit, $credit), 1);

        $byJournal = JournalEntry::query()
            ->whereDate('entry_date', '>=', $range[0])
            ->whereDate('entry_date', '<=', $range[1])
            ->selectRaw('journal, count(*) as total')
            ->groupBy('journal')
            ->orderBy('journal')
            ->toBase()
            ->get()
            ->map(fn ($row) => ['label' => (string) $row->journal, 'value' => (int) $row->total])
            ->all();

        $latest = JournalEntry::query()
            ->withSum('lines as total_debit', 'debit')
            ->orderByDesc('entry_date')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (JournalEntry $entry) => [
                $entry->journal.' · '.$entry->entry_date->format('d/m/Y'),
                $entry->description,
                number_format((int) $entry->getAttribute('total_debit'), 0, ',', ' ').' '.$entry->currency,
            ])
            ->all();

        return Widgets::section(
            'Comptabilité',
            'Accounting',
            '/comptabilite',
            [
                Widgets::kpi('entries', 'Écritures de la période', 'Entries in the period', $entries),
                Widgets::kpi('total_debit', 'Total débit', 'Total debit', $debit, Widgets::MONEY),
                Widgets::kpi('balance', 'Équilibre débit / crédit', 'Debit / credit balance', $balance, Widgets::PERCENT, $balanced ? Widgets::GOOD : Widgets::BAD),
            ],
            [
                Widgets::chart('entries_by_journal', 'Écritures par journal', 'Entries per journal', 'bar', $byJournal),
            ],
            [
                Widgets::table('latest_entries', 'Dernières écritures', 'Latest entries', [
                    ['Journal / date', 'Journal / date'], ['Libellé', 'Description'], ['Montant', 'Amount'],
                ], $latest),
            ],
        );
    }
}
