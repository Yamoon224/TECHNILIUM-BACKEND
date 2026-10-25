<?php

declare(strict_types=1);

use App\Modules\Shared\Exceptions\CurrencyMismatchException;
use App\Modules\Shared\ValueObjects\Currency;
use App\Modules\Shared\ValueObjects\Money;

it('adds two amounts of the same currency', function () {
    $a = Money::ofMinorUnits(1000, Currency::XOF);
    $b = Money::ofMinorUnits(500, Currency::XOF);

    expect($a->add($b)->amount)->toBe(1500);
});

it('refuses to add different currencies', function () {
    $a = Money::ofMinorUnits(1000, Currency::XOF);
    $b = Money::ofMinorUnits(500, Currency::EUR);

    expect(fn () => $a->add($b))->toThrow(CurrencyMismatchException::class);
});

it('subtracts amounts', function () {
    $a = Money::ofMinorUnits(1000, Currency::XOF);
    $b = Money::ofMinorUnits(300, Currency::XOF);

    expect($a->subtract($b)->amount)->toBe(700);
});

it('converts major units to minor units for a decimal currency', function () {
    expect(Money::ofMajorUnits(49.90, Currency::EUR)->amount)->toBe(4990);
});

it('keeps XOF as 1:1 since it has no minor subdivision', function () {
    expect(Money::ofMajorUnits(12500, Currency::XOF)->amount)->toBe(12500);
});

it('multiplies and rounds half up', function () {
    $amount = Money::ofMinorUnits(1005, Currency::XOF); // 10.05
    expect($amount->multiply(0.5)->amount)->toBe(503); // 5.025 -> 503 (round half up)
});

it('allocates an amount across N parts without losing or creating minor units', function (int $total, int $parts) {
    $money = Money::ofMinorUnits($total, Currency::XOF);
    $shares = $money->allocate($parts);

    expect($shares)->toHaveCount($parts);

    $sum = array_reduce($shares, fn (int $carry, Money $share) => $carry + $share->amount, 0);
    expect($sum)->toBe($total);
})->with([
    [10000, 3],
    [10, 3],
    [1, 7],
    [999_999, 13],
    [0, 4],
]);

it('reports zero and negative amounts correctly', function () {
    expect(Money::zero(Currency::XOF)->isZero())->toBeTrue();
    expect(Money::ofMinorUnits(-1, Currency::XOF)->isNegative())->toBeTrue();
});

it('compares amounts of the same currency', function () {
    $a = Money::ofMinorUnits(200, Currency::XOF);
    $b = Money::ofMinorUnits(100, Currency::XOF);

    expect($a->isGreaterThan($b))->toBeTrue();
    expect($b->isGreaterThan($a))->toBeFalse();
});

it('considers equal amount+currency as equal', function () {
    expect(Money::ofMinorUnits(100, Currency::XOF)->equals(Money::ofMinorUnits(100, Currency::XOF)))->toBeTrue();
    expect(Money::ofMinorUnits(100, Currency::XOF)->equals(Money::ofMinorUnits(100, Currency::EUR)))->toBeFalse();
});
