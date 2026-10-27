<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Domain;

/**
 * Technilium's own-stock margin on one delivered order (cahier §6.14
 * "rapports de marges"). Pure integer arithmetic, so every rule is tested once:
 *
 *  - the order's coupon discount is borne by Technilium, but only the share that
 *    falls on its OWN lines belongs to this margin (the share on a third-party
 *    vendor's lines is a marketplace promotion cost, not own-stock margin);
 *  - that share is spread over the own lines pro rata to their value;
 *  - revenue is VAT-exclusive, margin = revenue − cost of sales.
 */
final class MarginCalculator
{
    /**
     * @param  list<array{offer_id: int, product: string, quantity: int, line_total: int, cost: int}>  $lines  the order's own-stock lines
     * @param  int  $itemsTotal  list-price total of ALL the order's lines (own and third-party)
     * @param  int  $discount  the order's coupon discount
     * @param  int  $vatBasisPoints  e.g. 1800 for 18 %
     * @return list<array{offer_id: int, product: string, quantity: int, revenue: int, cost: int, margin: int}>
     */
    public static function orderLines(array $lines, int $itemsTotal, int $discount, int $vatBasisPoints): array
    {
        $ownTotal = array_sum(array_column($lines, 'line_total'));

        $ownDiscount = ($discount > 0 && $itemsTotal > 0 && $ownTotal > 0)
            ? min($discount, $ownTotal, intdiv($discount * $ownTotal + intdiv($itemsTotal, 2), $itemsTotal))
            : 0;

        $discountByLine = self::spread($ownDiscount, array_column($lines, 'line_total'));

        $result = [];

        foreach ($lines as $index => $line) {
            $revenue = self::excludingVat($line['line_total'] - $discountByLine[$index], $vatBasisPoints);

            $result[] = [
                'offer_id' => $line['offer_id'],
                'product' => $line['product'],
                'quantity' => $line['quantity'],
                'revenue' => $revenue,
                'cost' => $line['cost'],
                'margin' => $revenue - $line['cost'],
            ];
        }

        return $result;
    }

    /** VAT-inclusive amount to VAT-exclusive, half up. */
    public static function excludingVat(int $inclusive, int $vatBasisPoints): int
    {
        $divisor = 10_000 + max(0, $vatBasisPoints);

        return intdiv($inclusive * 10_000 + intdiv($divisor, 2), $divisor);
    }

    /** Margin as a percentage of revenue, one decimal; null when there is no revenue to compare to. */
    public static function percent(int $revenue, int $margin): ?float
    {
        return $revenue > 0 ? round($margin * 100 / $revenue, 1) : null;
    }

    /**
     * Splits an amount in proportion to the weights; the last part takes the
     * remainder so the parts add up to the amount exactly.
     *
     * @param  list<int>  $weights
     * @return list<int>
     */
    private static function spread(int $amount, array $weights): array
    {
        $total = array_sum($weights);

        if ($amount <= 0 || $total <= 0) {
            return array_fill(0, count($weights), 0);
        }

        $parts = [];
        $given = 0;
        $last = count($weights) - 1;

        foreach ($weights as $index => $weight) {
            $part = $index === $last ? $amount - $given : intdiv($amount * $weight + intdiv($total, 2), $total);
            $parts[] = $part;
            $given += $part;
        }

        return $parts;
    }
}
