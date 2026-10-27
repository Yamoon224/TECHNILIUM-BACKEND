<?php

declare(strict_types=1);

namespace App\Modules\Vendors\Http\Concerns;

use App\Modules\Vendors\Domain\VendorStatus;
use App\Modules\Vendors\Infrastructure\Models\Vendor;
use Illuminate\Http\Request;

/**
 * The vendor space is scoped to the authenticated user's own Active vendor
 * profile — the id never comes from the request, so a vendor cannot address
 * another vendor's data (cahier §8).
 */
trait ResolvesActiveVendor
{
    private function resolveActiveVendor(Request $request): Vendor
    {
        /** @var Vendor|null $vendor */
        $vendor = Vendor::query()->where('user_id', $request->user()->id)->first();

        if (! $vendor || $vendor->status !== VendorStatus::Active) {
            abort(403, "Ce compte n'a pas de profil vendeur actif.");
        }

        return $vendor;
    }
}
