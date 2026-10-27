<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application;

use App\Modules\Orders\Application\Contracts\OrderStatsInterface;
use App\Modules\Procurement\Application\Contracts\CostOfSalesInterface;
use App\Modules\Reporting\Domain\MarginCalculator;
use App\Modules\Returns\Application\Contracts\ReturnStatsInterface;
use DateTimeInterface;

/**
 * Technilium's own-stock gross margin over a period, built from the public
 * contracts of Orders (what was delivered), Procurement (what it cost) and
 * Returns (what came back). Third-party vendors' sales and commissions are
 * deliberately out: this is Technilium's own merchandise only.
 *
 * Margin is on DELIVERED orders, before returns; returns are reported beside
 * it, not netted — a refund covers a whole order, not just own-stock lines.
 */
final class MarginReport
{
    public function __construct(
        private readonly OrderStatsInterface $orders,
        private readonly CostOfSalesInterface $costs,
        private readonly ReturnStatsInterface $returns,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function build(DateTimeInterface $from, DateTimeInterface $to): array
    {
        $sales = $this->orders->deliveredOwnStockSales($from, $to);
        $costByOrder = $this->costs->costsByOrderAndOffer(array_column($sales, 'order_id'));
        $vatBasisPoints = (int) round(((float) config('invoicing.vat_percent')) * 100);

        $byProduct = [];
        $byDay = [];
        $uncostedRevenue = 0;
        $uncostedLines = 0;

        foreach ($sales as $sale) {
            $lines = array_map(fn (array $line) => $line + [
                'cost' => $costByOrder[$sale['order_id']][$line['offer_id']] ?? 0,
            ], $sale['lines']);

            foreach (MarginCalculator::orderLines($lines, $sale['items_total'], $sale['discount'], $vatBasisPoints) as $row) {
                $product = &$byProduct[$row['offer_id']];
                $product ??= ['offer_id' => $row['offer_id'], 'product' => $row['product'], 'units' => 0, 'revenue' => 0, 'cost' => 0, 'margin' => 0];
                $product['units'] += $row['quantity'];
                $product['revenue'] += $row['revenue'];
                $product['cost'] += $row['cost'];
                $product['margin'] += $row['margin'];
                unset($product);

                $day = &$byDay[$sale['delivered_on']];
                $day ??= ['date' => $sale['delivered_on'], 'revenue' => 0, 'cost' => 0, 'margin' => 0];
                $day['revenue'] += $row['revenue'];
                $day['cost'] += $row['cost'];
                $day['margin'] += $row['margin'];
                unset($day);

                // A line whose goods were never purchased through Procurement has no cost on
                // record: its "margin" is the whole revenue, which overstates reality.
                if ($row['cost'] === 0) {
                    $uncostedRevenue += $row['revenue'];
                    $uncostedLines++;
                }
            }
        }

        $products = array_map(fn (array $p) => $p + ['margin_percent' => MarginCalculator::percent($p['revenue'], $p['margin'])], array_values($byProduct));
        usort($products, fn (array $a, array $b) => $b['margin'] <=> $a['margin']);

        $revenue = array_sum(array_column($products, 'revenue'));
        $cost = array_sum(array_column($products, 'cost'));
        $margin = $revenue - $cost;

        ksort($byDay);

        return [
            'currency' => 'XOF',
            'period' => ['from' => $from->format('Y-m-d'), 'to' => $to->format('Y-m-d')],
            'scope' => 'own_stock_only',
            'vat_percent' => $vatBasisPoints / 100,
            'totals' => [
                'orders' => count($sales),
                'units' => array_sum(array_column($products, 'units')),
                'revenue' => $revenue,
                'cost' => $cost,
                'margin' => $margin,
                'margin_percent' => MarginCalculator::percent($revenue, $margin),
            ],
            'quality' => [
                // Revenue with no cost on record: the margin shown is overstated by up to this much.
                'uncosted_lines' => $uncostedLines,
                'uncosted_revenue' => $uncostedRevenue,
            ],
            'returns' => [
                'refunded_ttc' => $this->returns->refundedBetween($from, $to),
                'cost_given_back' => $this->costs->reversedBetween($from, $to),
            ],
            'by_product' => $products,
            'by_day' => array_map(
                fn (array $d) => $d + ['margin_percent' => MarginCalculator::percent($d['revenue'], $d['margin'])],
                array_values($byDay),
            ),
        ];
    }
}
