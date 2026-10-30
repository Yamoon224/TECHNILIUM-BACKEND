<?php

declare(strict_types=1);

namespace App\Modules\Procurement\Application\Dashboard;

use App\Modules\Procurement\Domain\PurchaseOrderStatus;
use App\Modules\Procurement\Infrastructure\Models\PurchaseOrder;
use App\Modules\Procurement\Infrastructure\Models\SupplierInvoice;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Purchasing block of the back-office home page. "Purchases" are the supplier
 * invoices dated in the period; the debt is what is still owed on all of them.
 * Amounts are whole XOF.
 */
final class ProcurementSection implements DashboardSection
{
    public function key(): string
    {
        return 'procurement';
    }

    public function ability(): string
    {
        return 'manage-procurement';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $open = fn (): Builder => PurchaseOrder::query()->whereIn('status', [
            PurchaseOrderStatus::Ordered->value,
            PurchaseOrderStatus::PartiallyReceived->value,
        ]);

        $openCount = $open()->count();
        $lateCount = $open()->whereNotNull('expected_at')->whereDate('expected_at', '<', now()->toDateString())->count();

        $unpaid = fn (): Builder => SupplierInvoice::query()->whereColumn('paid_amount', '<', 'amount');

        $unpaidCount = $unpaid()->count();
        $debt = (int) $unpaid()->sum(\Illuminate\Support\Facades\DB::raw('amount - paid_amount'));

        $inPeriod = fn (): Builder => SupplierInvoice::query()
            ->whereDate('invoice_date', '>=', $from->toDateString())
            ->whereDate('invoice_date', '<=', $to->toDateString());

        $purchases = (int) $inPeriod()->sum('amount');

        // By day for a short period, by month beyond two months.
        $byMonth = $from->diffInDays($to) > 62;
        $length = $byMonth ? 7 : 10;
        $series = [];
        $inPeriod()
            ->selectRaw('invoice_date, sum(amount) as total')
            ->groupBy('invoice_date')
            ->orderBy('invoice_date')
            ->get()
            ->each(function ($row) use (&$series, $length) {
                $key = substr((string) $row->getRawOriginal('invoice_date'), 0, $length);
                $series[$key] = ($series[$key] ?? 0) + (int) $row->total;
            });
        ksort($series);
        $points = [];
        foreach ($series as $label => $value) {
            $points[] = ['label' => (string) $label, 'value' => $value];
        }

        $rows = $unpaid()
            ->with('supplier:id,name')
            ->orderBy('due_date')
            ->orderBy('id')
            ->limit(8)
            ->get()
            ->map(fn (SupplierInvoice $i) => [
                $i->supplier->name,
                $i->number,
                $i->due_date->format('d/m/Y'),
                $i->outstanding(),
            ])->all();

        return Widgets::section(
            'Achats',
            'Purchasing',
            '/achats',
            [
                Widgets::kpi('po_open', 'Bons de commande ouverts', 'Open purchase orders', $openCount),
                Widgets::kpi('po_late', 'Bons de commande en retard', 'Late purchase orders', $lateCount, Widgets::NUMBER, $lateCount > 0 ? Widgets::BAD : null),
                Widgets::kpi('invoices_unpaid', 'Factures fournisseurs impayées', 'Unpaid supplier invoices', $unpaidCount, Widgets::NUMBER, $unpaidCount > 0 ? Widgets::WARN : null),
                Widgets::kpi('supplier_debt', 'Dette fournisseurs', 'Supplier debt', $debt, Widgets::MONEY, $debt > 0 ? Widgets::WARN : null),
                Widgets::kpi('purchases', 'Achats de la période', 'Purchases in the period', $purchases, Widgets::MONEY),
            ],
            [
                Widgets::chart('purchases_by_period', $byMonth ? 'Achats par mois' : 'Achats par jour', $byMonth ? 'Purchases by month' : 'Purchases by day', 'bar', $points, Widgets::MONEY),
            ],
            [
                Widgets::table('invoices_to_pay', 'Factures fournisseurs à régler', 'Supplier invoices to pay', [
                    ['Fournisseur', 'Supplier'],
                    ['N°', 'No.'],
                    ['Échéance', 'Due date'],
                    ['Reste à payer (XOF)', 'Remaining (XOF)'],
                ], $rows),
            ],
        );
    }
}
