<?php

declare(strict_types=1);

use App\Modules\Accounting\Application\Actions\PostJournalEntryAction;
use App\Modules\Accounting\Application\Dashboard\AccountingSection;
use App\Modules\Accounting\Domain\EntryLine;
use App\Modules\Identity\Domain\AccessCatalogue;
use Carbon\CarbonImmutable;

function accountingKpi(array $section, string $key): array
{
    return collect($section['kpis'])->firstWhere('key', $key);
}

function postEntry(string $journal, int $id, int $amount, string $description): void
{
    app(PostJournalEntryAction::class)->execute($journal, 'test', $id, $description, [
        new EntryLine('411', debit: $amount),
        new EntryLine('701', credit: $amount),
    ], 'XOF');
}

it('declares a known ability and a key', function () {
    $section = app(AccountingSection::class);

    expect($section->key())->toBe('accounting')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises the ledger of the period', function () {
    postEntry('VT', 1, 100_000, 'Vente 1');
    postEntry('VT', 2, 50_000, 'Vente 2');
    postEntry('MM', 3, 30_000, 'Paiement');

    $today = CarbonImmutable::now();
    $section = app(AccountingSection::class)->build($today->subDays(6)->startOfDay(), $today->endOfDay());

    expect($section['link'])->toBe('/comptabilite')
        ->and(accountingKpi($section, 'entries')['value'])->toBe(3)
        ->and(accountingKpi($section, 'total_debit')['value'])->toBe(180_000)
        ->and(accountingKpi($section, 'total_debit')['format'])->toBe('money')
        ->and(accountingKpi($section, 'balance')['value'])->toBe(100.0)
        ->and(accountingKpi($section, 'balance')['tone'])->toBe('good');

    $points = collect($section['charts'][0]['points'])->pluck('value', 'label');
    expect($points['VT'])->toBe(2)->and($points['MM'])->toBe(1);

    $rows = $section['tables'][0]['rows'];
    expect($rows)->toHaveCount(3)
        ->and($rows[0][1])->toBe('Paiement')
        ->and($rows[0][2])->toContain('30');
});

it('flags unbalanced books', function () {
    $entry = app(PostJournalEntryAction::class)->execute('VT', 'test', 9, 'Vente', [
        new EntryLine('411', debit: 100), new EntryLine('701', credit: 100),
    ], 'XOF');
    // Corrupt the ledger behind the application's back to prove the check is real.
    DB::table('journal_entry_lines')->where('journal_entry_id', $entry->id)->where('credit', 100)->update(['credit' => 60]);

    $today = CarbonImmutable::now();
    $section = app(AccountingSection::class)->build($today->subDay()->startOfDay(), $today->endOfDay());

    expect(accountingKpi($section, 'balance')['tone'])->toBe('bad')
        ->and(accountingKpi($section, 'balance')['value'])->toBe(60.0);
});

it('copes with an empty database', function () {
    $today = CarbonImmutable::now();
    $section = app(AccountingSection::class)->build($today->subDays(6)->startOfDay(), $today->endOfDay());

    expect(accountingKpi($section, 'entries')['value'])->toBe(0)
        ->and(accountingKpi($section, 'total_debit')['value'])->toBe(0)
        ->and(accountingKpi($section, 'balance')['tone'])->toBe('good')
        ->and($section['charts'][0]['points'])->toBe([])
        ->and($section['tables'][0]['rows'])->toBe([]);
});
