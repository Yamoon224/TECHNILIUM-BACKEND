<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Domain;

/**
 * Spreads a purchase's landed costs (transport, customs, handling — cahier
 * §6.4 "coût de revient réel") over what is actually received, so each
 * receipt carries its fair share and the shares add up to exactly the total
 * once everything is received, with no minor unit lost or invented.
 *
 * The share is computed on CUMULATIVE values: what had been allocated before
 * this receipt is subtracted from what should have been allocated after it.
 * That makes it correct when a cost is added late (the next receipt catches
 * up) and when receipts arrive in several parts.
 */
final class LandedCostAllocator
{
    /**
     * @param  int  $landedTotal  all landed costs recorded on the purchase
     * @param  int  $goodsTotal  value of everything ordered (the base the costs are spread over)
     * @param  int  $receivedBefore  cumulative value received before this receipt
     * @param  int  $receivedAfter  cumulative value received including this receipt
     */
    public static function shareForReceipt(int $landedTotal, int $goodsTotal, int $receivedBefore, int $receivedAfter): int
    {
        if ($landedTotal <= 0 || $goodsTotal <= 0) {
            return 0;
        }

        return self::allocatedTo($landedTotal, $goodsTotal, $receivedAfter)
            - self::allocatedTo($landedTotal, $goodsTotal, $receivedBefore);
    }

    /** Portion of the landed costs owed by `$received` value, half up, never above the total. */
    private static function allocatedTo(int $landedTotal, int $goodsTotal, int $received): int
    {
        if ($received >= $goodsTotal) {
            return $landedTotal;
        }

        return max(0, min($landedTotal, intdiv($landedTotal * $received + intdiv($goodsTotal, 2), $goodsTotal)));
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
