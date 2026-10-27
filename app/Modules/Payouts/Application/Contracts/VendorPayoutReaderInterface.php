<?php

declare(strict_types=1);

namespace App\Modules\Payouts\Application\Contracts;

use App\Modules\Payouts\Application\Data\VendorPayoutData;

/**
 * Payouts' public port for the vendor space. Every read is scoped to a
 * vendor id: a payout of another vendor is indistinguishable from a missing
 * one (cahier §8 cloisonnement).
 */
interface VendorPayoutReaderInterface
{
    /** @return list<VendorPayoutData> */
    public function listFor(int $vendorId): array;

    /** Null when the payout does not exist OR belongs to another vendor. */
    public function findFor(int $vendorId, int $payoutId): ?VendorPayoutData;
}
