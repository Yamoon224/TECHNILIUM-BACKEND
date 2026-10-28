<?php

declare(strict_types=1);

use App\Modules\Invoicing\Domain\AmountInWordsEn;

it('spells whole numbers in English', function (int $n, string $words) {
    expect(AmountInWordsEn::number($n))->toBe($words);
})->with([
    [0, 'zero'],
    [1, 'one'],
    [13, 'thirteen'],
    [20, 'twenty'],
    [21, 'twenty-one'],
    [99, 'ninety-nine'],
    [100, 'one hundred'],
    [118, 'one hundred eighteen'],
    [1_000, 'one thousand'],
    [118_000, 'one hundred eighteen thousand'],
    [101_001, 'one hundred one thousand one'],
    [1_000_000, 'one million'],
    [2_500_000, 'two million five hundred thousand'],
    [1_000_000_000, 'one billion'],
    [1_234_567_890, 'one billion two hundred thirty-four million five hundred sixty-seven thousand eight hundred ninety'],
]);

it('names the currency, singular only for one', function () {
    expect(AmountInWordsEn::francsCfa(1))->toBe('one CFA franc')
        ->and(AmountInWordsEn::francsCfa(0))->toBe('zero CFA francs')
        ->and(AmountInWordsEn::francsCfa(118_000))->toBe('one hundred eighteen thousand CFA francs');
});

it('refuses a negative amount', function () {
    AmountInWordsEn::number(-1);
})->throws(InvalidArgumentException::class);
