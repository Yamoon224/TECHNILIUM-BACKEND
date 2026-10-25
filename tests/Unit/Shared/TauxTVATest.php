<?php

declare(strict_types=1);

use App\Modules\Shared\ValueObjects\Currency;
use App\Modules\Shared\ValueObjects\Money;
use App\Modules\Shared\ValueObjects\TauxTVA;

it('applies an 18% VAT rate to an amount', function () {
    $rate = TauxTVA::ofPercent(18);
    $amount = Money::ofMinorUnits(10_000, Currency::XOF);

    expect($rate->applyTo($amount)->amount)->toBe(1_800);
});

it('round-trips the percent representation', function () {
    expect(TauxTVA::ofPercent(18)->toPercent())->toBe(18.0);
    expect(TauxTVA::ofPercent(5.5)->toPercent())->toBe(5.5);
});

it('treats zero rate as a no-op', function () {
    $amount = Money::ofMinorUnits(10_000, Currency::XOF);
    expect(TauxTVA::zero()->applyTo($amount)->isZero())->toBeTrue();
});
