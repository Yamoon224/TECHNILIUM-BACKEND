<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain;

/**
 * Coût moyen pondéré (CMP, cahier §6.3 / glossaire): the unit cost of stock
 * is re-averaged at each receipt, weighting what is already on hand by its
 * current average and what arrives by its full landed cost.
 */
final class AverageCost
{
    /**
     * @param  int  $onHandQuantity  stock on hand just before the receipt
     * @param  int  $currentAverage  unit cost of that stock (0 when unknown)
     * @param  int  $receivedQuantity  accepted quantity arriving
     * @param  int  $receivedTotalCost  what that quantity cost in all (goods + its landed share)
     */
    public static function next(int $onHandQuantity, int $currentAverage, int $receivedQuantity, int $receivedTotalCost): int
    {
        if ($receivedQuantity <= 0) {
            return max(0, $currentAverage);
        }

        // No known cost for what is on hand (never purchased through here):
        // the new unit cost stands alone rather than being dragged down by a phantom zero.
        if ($onHandQuantity <= 0 || $currentAverage <= 0) {
            return intdiv($receivedTotalCost + intdiv($receivedQuantity, 2), $receivedQuantity);
        }

        $totalQuantity = $onHandQuantity + $receivedQuantity;
        $totalCost = $onHandQuantity * $currentAverage + $receivedTotalCost;

        return intdiv($totalCost + intdiv($totalQuantity, 2), $totalQuantity);
    }
}
