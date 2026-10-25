<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\OfferStatus;

it('allows the legal moderation transitions', function (OfferStatus $from, OfferStatus $to) {
    expect($from->canTransitionTo($to))->toBeTrue();
})->with([
    [OfferStatus::Draft, OfferStatus::PendingReview],
    [OfferStatus::PendingReview, OfferStatus::Published],
    [OfferStatus::PendingReview, OfferStatus::Rejected],
    [OfferStatus::Rejected, OfferStatus::Draft],
    [OfferStatus::Published, OfferStatus::Archived],
]);

it('forbids skipping moderation or resurrecting an archived offer', function (OfferStatus $from, OfferStatus $to) {
    expect($from->canTransitionTo($to))->toBeFalse();
})->with([
    [OfferStatus::Draft, OfferStatus::Published],
    [OfferStatus::Published, OfferStatus::Draft],
    [OfferStatus::Archived, OfferStatus::Published],
    [OfferStatus::Archived, OfferStatus::Draft],
]);

it('has no outgoing transition once archived (terminal state)', function () {
    expect(OfferStatus::Archived->allowedTransitions())->toBe([]);
});
