<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Actions;

use App\Modules\Catalog\Domain\Exceptions\InvalidOfferTransitionException;
use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use Illuminate\Support\Facades\DB;

/**
 * Single entry point for every offer status change — mirrors the "machine à
 * états" requirement (cahier §6.1 file de validation / §10.2) so a status
 * is never written to the database outside of an authorized transition.
 */
final class TransitionOfferStatusAction
{
    public function execute(Offer $offer, OfferStatus $target): Offer
    {
        $current = $offer->status;

        if (! $current->canTransitionTo($target)) {
            throw InvalidOfferTransitionException::from($current, $target);
        }

        DB::transaction(function () use ($offer, $target) {
            $offer->status = $target;
            $offer->published_at = $target === OfferStatus::Published ? now() : $offer->published_at;
            $offer->save();
        });

        return $offer->refresh();
    }
}
