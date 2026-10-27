<?php

declare(strict_types=1);

use App\Modules\Customers\Application\Actions\SubmitReviewAction;
use App\Modules\Customers\Domain\RatingSummary;
use App\Modules\Customers\Domain\ReviewScreener;

it('lets an ordinary review through', function (string $text) {
    expect(ReviewScreener::needsManualReview($text))->toBeFalse();
})->with([
    'Très bon téléphone, la batterie tient deux jours.',
    'Livraison rapide, produit conforme. Je recommande !',
    'Écran 6,7 pouces, 128 Go, 5000 mAh.',          // numbers, but not a phone number
    'Reçu le 12/10/2026 en bon état.',
]);

it('holds a link, an e-mail or a phone number for manual review', function (string $text) {
    expect(ReviewScreener::needsManualReview($text))->toBeTrue();
})->with([
    'link with scheme' => ['Meilleur prix sur https://boutique-pas-chere.example/promo'],
    'link with www' => ['Allez voir www.promo-tech.net'],
    'bare domain' => ['Commandez sur techdeals.ci pour moins cher'],
    'e-mail' => ['Écrivez-moi à moi@exemple.com'],
    'phone plain' => ['Appelez le 0700000001 pour une remise'],
    'phone spaced' => ['Mon numéro : 07 00 00 00 01'],
    'phone international' => ['WhatsApp +225 07-00-00-00-01'],
]);

it('holds a review containing a banned word, ignoring case and blanks in the list', function () {
    expect(ReviewScreener::needsManualReview('Quelle ARNAQUE !', ['arnaque']))->toBeTrue()
        ->and(ReviewScreener::needsManualReview('Quelle arnaque !', [' ', ''])) ->toBeFalse()
        ->and(ReviewScreener::needsManualReview('Parfait.', ['arnaque']))->toBeFalse();
});

it('summarises ratings: count, average to one decimal, and the full distribution', function () {
    $summary = RatingSummary::fromCounts([5 => 3, 4 => 1, 1 => 1]);

    expect($summary['count'])->toBe(5)
        // (15 + 4 + 1) / 5 = 4.0
        ->and($summary['average'])->toBe(4.0)
        ->and($summary['distribution'])->toBe([5 => 3, 4 => 1, 3 => 0, 2 => 0, 1 => 1]);
});

it('rounds the average half up to one decimal', function () {
    // (5 + 4 + 4) / 3 = 4.333… -> 4.3
    expect(RatingSummary::fromCounts([5 => 1, 4 => 2])['average'])->toBe(4.3);
});

it('has a zero average and an all-zero distribution when there are no reviews', function () {
    $summary = RatingSummary::fromCounts([]);

    expect($summary)->toBe(['count' => 0, 'average' => 0.0, 'distribution' => [5 => 0, 4 => 0, 3 => 0, 2 => 0, 1 => 0]]);
});

it('ignores a negative count', function () {
    expect(RatingSummary::fromCounts([5 => -3, 4 => 2])['count'])->toBe(2);
});

it('shows only a first name and the initial of the last name in public', function (string $full, string $public) {
    expect(SubmitReviewAction::publicName($full))->toBe($public);
})->with([
    ['Awa Diallo', 'Awa D.'],
    ['Awa Marie Diallo', 'Awa D.'],
    ['  Awa   Diallo ', 'Awa D.'],
    ['Awa', 'Awa'],
    ['émile zola', 'émile Z.'],
    ['', 'Client'],
]);
