<?php

declare(strict_types=1);

use App\Modules\Shared\ValueObjects\NumeroSerie;

it('accepts a valid IMEI (Luhn checksum passes)', function () {
    $serial = NumeroSerie::fromString('490154203237518');

    expect($serial->value)->toBe('490154203237518');
    expect($serial->isImei())->toBeTrue();
});

it('rejects a 15-digit value with an invalid IMEI checksum', function () {
    expect(fn () => NumeroSerie::fromString('490154203237517'))->toThrow(InvalidArgumentException::class);
});

it('accepts a generic alphanumeric serial number', function () {
    $serial = NumeroSerie::fromString('snxk2024a1b2c3');

    expect($serial->value)->toBe('SNXK2024A1B2C3');
    expect($serial->isImei())->toBeFalse();
});

it('rejects an empty serial number', function () {
    expect(fn () => NumeroSerie::fromString(''))->toThrow(InvalidArgumentException::class);
});
