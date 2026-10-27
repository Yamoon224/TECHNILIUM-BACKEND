<?php

declare(strict_types=1);

namespace App\Modules\Customers\Domain;

/**
 * Average and distribution of a product's published ratings, from the count
 * per star. Pure so the arithmetic (and its empty case) is tested once.
 */
final class RatingSummary
{
    /**
     * @param  array<int, int>  $countByStars  stars (1-5) => number of reviews; missing stars count as 0
     * @return array{count: int, average: float, distribution: array<int, int>}
     */
    public static function fromCounts(array $countByStars): array
    {
        $distribution = [];
        $total = 0;
        $sum = 0;

        foreach ([5, 4, 3, 2, 1] as $stars) {
            $count = max(0, (int) ($countByStars[$stars] ?? 0));
            $distribution[$stars] = $count;
            $total += $count;
            $sum += $stars * $count;
        }

        return [
            'count' => $total,
            // One decimal, half up; 0.0 when there is nothing to average.
            'average' => $total > 0 ? round($sum / $total, 1) : 0.0,
            'distribution' => $distribution,
        ];
    }
}
