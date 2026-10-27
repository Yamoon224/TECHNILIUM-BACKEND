<?php

declare(strict_types=1);

use App\Modules\Invoicing\Domain\VatBreakdown;
use App\Modules\Shared\ValueObjects\Currency;
use App\Modules\Shared\ValueObjects\Money;
use App\Modules\Shared\ValueObjects\TauxTVA;

it('splits a VAT-inclusive total into net and VAT', function () {
    $breakdown = VatBreakdown::fromGross(Money::ofMinorUnits(118_000, Currency::XOF), TauxTVA::ofPercent(18));

    expect($breakdown->net->amount)->toBe(100_000)
        ->and($breakdown->vat->amount)->toBe(18_000)
        ->and($breakdown->gross->amount)->toBe(118_000);
});

it('always makes net + VAT equal the gross total', function (int $gross) {
    $b = VatBreakdown::fromGross(Money::ofMinorUnits($gross, Currency::XOF), TauxTVA::ofPercent(18));

    expect($b->net->add($b->vat)->amount)->toBe($gross);
})->with([1, 7, 999, 12_345, 1_000_001]);

it('yields no VAT at a zero rate', function () {
    $b = VatBreakdown::fromGross(Money::ofMinorUnits(5_000, Currency::XOF), TauxTVA::zero());

    expect($b->vat->amount)->toBe(0)->and($b->net->amount)->toBe(5_000);
});
