<?php

declare(strict_types=1);

use App\Modules\Catalog\Application\Actions\TransitionOfferStatusAction;
use App\Modules\Catalog\Domain\Exceptions\InvalidOfferTransitionException;
use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;

it('moves a pending offer to published and stamps published_at', function () {
    $offer = Offer::factory()->pendingReview()->create();

    $result = (new TransitionOfferStatusAction)->execute($offer, OfferStatus::Published);

    expect($result->status)->toBe(OfferStatus::Published);
    expect($result->published_at)->not->toBeNull();
});

it('refuses an illegal transition and leaves the offer untouched', function () {
    $offer = Offer::factory()->create(['status' => OfferStatus::Draft]);

    expect(fn () => (new TransitionOfferStatusAction)->execute($offer, OfferStatus::Published))
        ->toThrow(InvalidOfferTransitionException::class);

    expect($offer->fresh()->status)->toBe(OfferStatus::Draft);
});
