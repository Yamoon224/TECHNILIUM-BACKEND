<?php

declare(strict_types=1);

use App\Modules\Shared\ValueObjects\Quantity;

it('adds quantities', function () {
    expect(Quantity::of(2)->add(Quantity::of(3))->value)->toBe(5);
});

it('subtracts quantities', function () {
    expect(Quantity::of(5)->subtract(Quantity::of(2))->value)->toBe(3);
});

it('refuses a negative result', function () {
    expect(fn () => Quantity::of(1)->subtract(Quantity::of(2)))->toThrow(InvalidArgumentException::class);
});

it('refuses a negative quantity at construction', function () {
    expect(fn () => Quantity::of(-1))->toThrow(InvalidArgumentException::class);
});

it('never reports available stock as negative', function (int $value) {
    expect(Quantity::of($value)->value)->toBeGreaterThanOrEqual(0);
})->with([0, 1, 1000]);
