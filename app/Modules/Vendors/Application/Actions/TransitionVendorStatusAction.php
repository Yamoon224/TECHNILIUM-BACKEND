<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Application\Actions;

use App\Modules\Vendors\Domain\Exceptions\InvalidVendorTransitionException;
use App\Modules\Vendors\Domain\VendorStatus;
use App\Modules\Vendors\Infrastructure\Models\Vendor;

/**
 * Single entry point for every vendor status change — mirrors Catalog's
 * TransitionOfferStatusAction so a status is never written outside of an
 * authorized transition.
 */
final class TransitionVendorStatusAction
{
    public function execute(Vendor $vendor, VendorStatus $target): Vendor
    {
        $current = $vendor->status;

        if (! $current->canTransitionTo($target)) {
            throw InvalidVendorTransitionException::from($current, $target);
        }

        $vendor->status = $target;
        $vendor->save();

        return $vendor->refresh();
    }
}
