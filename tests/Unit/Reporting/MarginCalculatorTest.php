<?php

declare(strict_types=1);

use App\Modules\Reporting\Domain\MarginCalculator;

function ownLine(int $offerId, int $lineTotal, int $cost, int $quantity = 1): array
{
    return ['offer_id' => $offerId, 'product' => "Produit {$offerId}", 'quantity' => $quantity, 'line_total' => $lineTotal, 'cost' => $cost];
}

it('takes VAT out of a VAT-inclusive amount, half up', function () {
    expect(MarginCalculator::excludingVat(118_000, 1800))->toBe(100_000)
        ->and(MarginCalculator::excludingVat(1_500, 1800))->toBe(1_271)    // 1 271.19
        ->and(MarginCalculator::excludingVat(1_000, 1800))->toBe(847)      // 847.46
        ->and(MarginCalculator::excludingVat(5_000, 0))->toBe(5_000);      // no VAT
});

it('computes revenue excluding VAT and margin = revenue − cost, with no discount', function () {
    $rows = MarginCalculator::orderLines([ownLine(1, 118_000, 70_000)], 118_000, 0, 1800);

    expect($rows)->toHaveCount(1)
        ->and($rows[0]['revenue'])->toBe(100_000)
        ->and($rows[0]['cost'])->toBe(70_000)
        ->and($rows[0]['margin'])->toBe(30_000);
});

it('takes the whole discount off an order made only of own stock', function () {
    // 118 000 TTC, 11 800 discount: customer pays 106 200 TTC = 90 000 HT.
    $rows = MarginCalculator::orderLines([ownLine(1, 118_000, 60_000)], 118_000, 11_800, 1800);

    expect($rows[0]['revenue'])->toBe(90_000)->and($rows[0]['margin'])->toBe(30_000);
});

it('charges the own lines only their share of a discount on a mixed order', function () {
    // Own 60 000 + a vendor's 40 000 = 100 000; discount 10 000 -> own share 6 000 -> 54 000 TTC.
    $rows = MarginCalculator::orderLines([ownLine(1, 60_000, 20_000)], 100_000, 10_000, 1800);

    expect($rows[0]['revenue'])->toBe(MarginCalculator::excludingVat(54_000, 1800));
});

it('spreads the own discount across several own lines so the parts add up exactly', function () {
    $lines = [ownLine(1, 10_000, 0), ownLine(2, 10_000, 0), ownLine(3, 10_000, 0)];

    $withDiscount = MarginCalculator::orderLines($lines, 30_000, 100, 0);   // VAT 0: revenue is the net TTC itself
    $sum = array_sum(array_column($withDiscount, 'revenue'));

    expect($sum)->toBe(30_000 - 100);   // 100 / 3 is not whole: the remainder lands on the last line, none is lost
});

it('never gives more discount than the own lines are worth', function () {
    $rows = MarginCalculator::orderLines([ownLine(1, 1_000, 0)], 1_000, 999_999, 0);

    expect($rows[0]['revenue'])->toBe(0);
});

it('handles an order with no discount or no own value without dividing by zero', function () {
    expect(MarginCalculator::orderLines([ownLine(1, 5_000, 1_000)], 5_000, 0, 1800)[0]['revenue'])->toBe(4_237)
        ->and(MarginCalculator::orderLines([ownLine(1, 0, 0)], 0, 500, 1800)[0]['revenue'])->toBe(0)
        ->and(MarginCalculator::orderLines([], 0, 0, 1800))->toBe([]);
});

it('shows a negative margin when a sale is below cost', function () {
    $rows = MarginCalculator::orderLines([ownLine(1, 11_800, 15_000)], 11_800, 0, 1800);

    expect($rows[0]['margin'])->toBe(10_000 - 15_000);
});

it('expresses margin as a percentage of revenue, one decimal, and nothing when there is no revenue', function () {
    expect(MarginCalculator::percent(100_000, 30_000))->toBe(30.0)
        ->and(MarginCalculator::percent(1_271, 971))->toBe(76.4)
        ->and(MarginCalculator::percent(100, -50))->toBe(-50.0)
        ->and(MarginCalculator::percent(0, 0))->toBeNull()
        ->and(MarginCalculator::percent(0, -10))->toBeNull();
});
