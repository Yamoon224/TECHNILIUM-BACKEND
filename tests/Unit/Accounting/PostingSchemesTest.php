<?php

declare(strict_types=1);

use App\Modules\Accounting\Domain\EntryLine;
use App\Modules\Accounting\Domain\PostingSchemes;

function totals(array $lines): array
{
    return [
        array_sum(array_map(fn (EntryLine $l) => $l->debit, $lines)),
        array_sum(array_map(fn (EntryLine $l) => $l->credit, $lines)),
    ];
}

it('books a sale: receivable debited, revenue and VAT credited', function () {
    $lines = PostingSchemes::invoice(100_000, 18_000, 118_000, false);

    expect($lines)->toHaveCount(3)
        ->and($lines[0]->account)->toBe('411')->and($lines[0]->debit)->toBe(118_000)
        ->and($lines[1]->account)->toBe('701')->and($lines[1]->credit)->toBe(100_000)
        ->and($lines[2]->account)->toBe('4431')->and($lines[2]->credit)->toBe(18_000);
});

it('omits the VAT line when there is no VAT', function () {
    expect(PostingSchemes::invoice(5_000, 0, 5_000, false))->toHaveCount(2);
});

it('mirrors a credit note and keeps every entry balanced', function () {
    $lines = PostingSchemes::invoice(100_000, 18_000, 118_000, true);

    expect($lines[0]->credit)->toBe(118_000)->and($lines[0]->debit)->toBe(0);
    [$d, $c] = totals($lines);
    expect($d)->toBe($c);
});

it('routes collections to the right treasury account and journal', function () {
    expect(PostingSchemes::payment('cod', 500, false)[0]->account)->toBe('571')
        ->and(PostingSchemes::payment('mobile_money', 500, false)[0]->account)->toBe('585')
        ->and(PostingSchemes::paymentJournal('mobile_money'))->toBe('MM')
        ->and(PostingSchemes::paymentJournal('cod'))->toBe('CA');
});

it('reverses a payment on refund', function () {
    $lines = PostingSchemes::payment('cod', 500, true);

    expect($lines[0]->credit)->toBe(500)->and($lines[1]->debit)->toBe(500);
});
