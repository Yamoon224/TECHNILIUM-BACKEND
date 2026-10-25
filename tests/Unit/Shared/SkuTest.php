<?php

declare(strict_types=1);

use App\Modules\Shared\ValueObjects\Sku;

it('normalizes to uppercase', function () {
    expect(Sku::fromString('phone-black-128gb')->value)->toBe('PHONE-BLACK-128GB');
});

it('rejects an empty value', function () {
    expect(fn () => Sku::fromString('  '))->toThrow(InvalidArgumentException::class);
});

it('rejects invalid characters', function () {
    expect(fn () => Sku::fromString('SKU WITH SPACE'))->toThrow(InvalidArgumentException::class);
});

it('compares two SKUs for equality', function () {
    expect(Sku::fromString('ABC-1')->equals(Sku::fromString('abc-1')))->toBeTrue();
});
