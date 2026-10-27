<?php

declare(strict_types=1);

use App\Modules\Invoicing\Domain\AmountInWords;

it('spells numbers in French', function (int $n, string $words) {
    expect(AmountInWords::number($n))->toBe($words);
})->with([
    [0, 'zéro'],
    [1, 'un'],
    [16, 'seize'],
    [21, 'vingt et un'],
    [31, 'trente et un'],
    [22, 'vingt-deux'],
    [60, 'soixante'],
    [71, 'soixante et onze'],
    [72, 'soixante-douze'],
    [79, 'soixante-dix-neuf'],
    [80, 'quatre-vingts'],
    [81, 'quatre-vingt-un'],
    [90, 'quatre-vingt-dix'],
    [91, 'quatre-vingt-onze'],
    [99, 'quatre-vingt-dix-neuf'],
    [100, 'cent'],
    [101, 'cent un'],
    [180, 'cent quatre-vingts'],
    [200, 'deux cents'],
    [201, 'deux cent un'],
    [999, 'neuf cent quatre-vingt-dix-neuf'],
]);

it('handles thousands: "mille" is invariable, and cent / quatre-vingt lose their s before it', function (int $n, string $words) {
    expect(AmountInWords::number($n))->toBe($words);
})->with([
    [1_000, 'mille'],
    [1_001, 'mille un'],
    [2_000, 'deux mille'],
    [21_000, 'vingt et un mille'],
    [80_000, 'quatre-vingt mille'],
    [200_000, 'deux cent mille'],
    [118_000, 'cent dix-huit mille'],
    [250_000, 'deux cent cinquante mille'],
    [999_999, 'neuf cent quatre-vingt-dix-neuf mille neuf cent quatre-vingt-dix-neuf'],
    [1_250, 'mille deux cent cinquante'],
]);

it('treats million and milliard as nouns that agree and keep the s on cents', function (int $n, string $words) {
    expect(AmountInWords::number($n))->toBe($words);
})->with([
    [1_000_000, 'un million'],
    [2_000_000, 'deux millions'],
    [200_000_000, 'deux cents millions'],
    [1_500_000, 'un million cinq cent mille'],
    [1_000_000_000, 'un milliard'],
    [3_000_000_000, 'trois milliards'],
    [2_001_001, 'deux millions mille un'],
]);

it('writes an amount in francs CFA, singular for one and "de" before a round million', function () {
    expect(AmountInWords::francsCfa(1))->toBe('un franc CFA')
        ->and(AmountInWords::francsCfa(0))->toBe('zéro franc CFA')
        ->and(AmountInWords::francsCfa(118_000))->toBe('cent dix-huit mille francs CFA')
        ->and(AmountInWords::francsCfa(5_000_000))->toBe('cinq millions de francs CFA')
        ->and(AmountInWords::francsCfa(5_000_001))->toBe('cinq millions un francs CFA');
});

it('refuses a negative amount', function () {
    expect(fn () => AmountInWords::number(-1))->toThrow(InvalidArgumentException::class);
});
