<?php

declare(strict_types=1);

namespace App\Modules\Sales\Application;

use App\Modules\Sales\Infrastructure\Models\CashClosing;
use App\Modules\Sales\Infrastructure\Models\InternalSale;
use App\Modules\Shared\Support\BrandLogo;
use Barryvdh\DomPDF\Facade\Pdf;

/** The signed till report ("ticket Z"): what was expected, what was counted, the variance, every sale. */
class RenderClosingReport
{
    /**
     * @return array<string, string>
     */
    public static function labels(string $locale): array
    {
        return $locale === 'en'
            ? [
                'lang' => 'en', 'title' => 'TILL CLOSING', 'number' => 'No.', 'closed' => 'Closed on', 'cashier' => 'Cashier',
                'period' => 'Period since', 'sales' => 'Sales', 'cancellations' => 'Cancellations', 'cash_sales' => 'Cash sales',
                'cash_refunds' => 'Cash refunded', 'expected' => 'Expected cash', 'counted' => 'Counted cash', 'variance' => 'Variance',
                'mobile' => 'Mobile money (net)', 'order' => 'Order', 'method' => 'Method', 'amount' => 'Amount', 'cancelled' => 'cancelled',
                'cash' => 'Cash', 'mobile_money' => 'Mobile money', 'note' => 'Note', 'signature' => 'Cashier signature',
            ]
            : [
                'lang' => 'fr', 'title' => 'CLÔTURE DE CAISSE', 'number' => 'N°', 'closed' => 'Clôturée le', 'cashier' => 'Caissier',
                'period' => 'Période depuis', 'sales' => 'Ventes', 'cancellations' => 'Annulations', 'cash_sales' => 'Ventes en espèces',
                'cash_refunds' => 'Espèces remboursées', 'expected' => 'Espèces attendues', 'counted' => 'Espèces comptées', 'variance' => 'Écart',
                'mobile' => 'Mobile money (net)', 'order' => 'Commande', 'method' => 'Mode', 'amount' => 'Montant', 'cancelled' => 'annulée',
                'cash' => 'Espèces', 'mobile_money' => 'Mobile money', 'note' => 'Note', 'signature' => 'Signature du caissier',
            ];
    }

    public function number(CashClosing $closing): string
    {
        return 'CLO-'.sprintf('%06d', $closing->id);
    }

    public function html(CashClosing $closing, string $cashierName, string $locale = 'fr'): string
    {
        $t = self::labels($locale === 'en' ? 'en' : 'fr');
        $money = fn (int $amount) => number_format($amount, 0, ',', "\u{00A0}")."\u{00A0}".($closing->currency === 'XOF' ? "F\u{00A0}CFA" : $closing->currency);

        $rows = InternalSale::query()
            ->where(fn ($q) => $q->where('closing_id', $closing->id)->orWhere('cancel_closing_id', $closing->id))
            ->orderBy('id')
            ->get()
            ->map(fn (InternalSale $sale) => [
                'order' => $sale->order_number,
                'method' => $t[$sale->method] ?? $sale->method,
                'amount' => $money($sale->amount),
                'cancelled' => $sale->cancel_closing_id === $closing->id,
            ])->all();

        return view('sales::closing', [
            't' => $t,
            'logo' => BrandLogo::dataUri(),
            'company' => (array) config('invoicing.company'),
            'closing' => $closing,
            'number' => $this->number($closing),
            'cashier' => $cashierName,
            'rows' => $rows,
            'cashSales' => $money($closing->cash_sales),
            'cashRefunds' => $money($closing->cash_refunds),
            'expected' => $money($closing->expected_cash),
            'counted' => $money($closing->counted_cash),
            'variance' => ($closing->variance > 0 ? '+' : '').$money($closing->variance),
            'mobile' => $money($closing->mobile_money_net),
        ])->render();
    }

    public function pdf(CashClosing $closing, string $cashierName, string $locale = 'fr'): string
    {
        return Pdf::loadHTML($this->html($closing, $cashierName, $locale))->setPaper('a5', 'portrait')->setOption('isRemoteEnabled', false)->output();
    }
}
