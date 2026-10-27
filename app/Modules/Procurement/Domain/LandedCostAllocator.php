<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain;

/**
 * Spreads a purchase's landed costs (transport, customs, handling — cahier
 * §6.4 "coût de revient réel") over what is actually received, so each
 * receipt carries its fair share and the shares add up to exactly the total
 * once everything is received, with no minor unit lost or invented.
 *
 * The share is what SHOULD have been allocated once this receipt is counted
 * (cumulative received value / goods total x landed total), minus what earlier
 * receipts ACTUALLY carried. Comparing to what was really allocated — not to a
 * recomputation — is what makes a cost added after a first receipt catch up on
 * the next one instead of being lost.
 */
final class LandedCostAllocator
{
    /**
     * @param  int  $landedTotal  all landed costs recorded on the purchase so far
     * @param  int  $goodsTotal  value of everything ordered (the base the costs are spread over)
     * @param  int  $receivedAfter  cumulative value received, including this receipt
     * @param  int  $alreadyAllocated  landed costs carried by the earlier receipts
     */
    public static function shareForReceipt(int $landedTotal, int $goodsTotal, int $receivedAfter, int $alreadyAllocated): int
    {
        if ($landedTotal <= 0 || $goodsTotal <= 0) {
            return 0;
        }

        $dueToDate = $receivedAfter >= $goodsTotal
            ? $landedTotal
            : max(0, min($landedTotal, intdiv($landedTotal * $receivedAfter + intdiv($goodsTotal, 2), $goodsTotal)));

        return max(0, $dueToDate - $alreadyAllocated);
    }

    /**
     * Splits a receipt's landed share across its lines by value; the last
     * line takes the remainder so the parts sum to the share exactly.
     *
     * @param  list<int>  $lineValues
     * @return list<int>
     */
    public static function splitAcrossLines(int $share, array $lineValues): array
    {
        $total = array_sum($lineValues);

        if ($share <= 0 || $total <= 0) {
            return array_fill(0, count($lineValues), 0);
        }

        $parts = [];
        $allocated = 0;
        $last = count($lineValues) - 1;

        foreach ($lineValues as $index => $value) {
            $part = $index === $last
                ? $share - $allocated
                : intdiv($share * $value + intdiv($total, 2), $total);

            $parts[] = $part;
            $allocated += $part;
        }

        return $parts;
    }
}
