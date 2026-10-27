<?php

declare(strict_types=1);

use App\Modules\Payouts\Domain\CommissionCalculator;

it('takes 10 % of a gross amount', function () {
    expect(CommissionCalculator::fromPercent(10)->commissionOn(100_000))->toBe(10_000);
});

it('rounds half up to the minor unit', function () {
    // 10 % of 15 = 1.5 -> 2 ; 10 % of 14 = 1.4 -> 1
    expect(CommissionCalculator::fromPercent(10)->commissionOn(15))->toBe(2)
        ->and(CommissionCalculator::fromPercent(10)->commissionOn(14))->toBe(1);
});

it('never exceeds the gross amount, so the net is never negative', function (int $gross) {
    expect(CommissionCalculator::fromPercent(100)->commissionOn($gross))->toBeLessThanOrEqual($gross);
})->with([0, 1, 999, 123_457]);

it('rejects a rate outside 0-100 %', function () {
    expect(fn () => CommissionCalculator::fromPercent(101))->toThrow(InvalidArgumentException::class);
    expect(fn () => CommissionCalculator::fromPercent(-1))->toThrow(InvalidArgumentException::class);
});
