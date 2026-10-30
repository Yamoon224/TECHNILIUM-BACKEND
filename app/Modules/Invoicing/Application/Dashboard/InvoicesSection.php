<?php

declare(strict_types=1);

namespace App\Modules\Invoicing\Application\Dashboard;

use App\Modules\Invoicing\Domain\InvoiceType;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

/**
 * Dashboard block of the invoicing feature: documents issued in the period,
 * amount invoiced per day, and the latest documents. Amounts are XOF integers.
 */
final class InvoicesSection implements DashboardSection
{
    private const MAX_POINTS = 31;

    public function key(): string
    {
        return 'invoices';
    }

    public function ability(): string
    {
        return 'manage-invoices';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $stats = Invoice::query()
            ->whereBetween('issued_at', [$from, $to])
            ->selectRaw('type, count(*) as documents, coalesce(sum(total_amount), 0) as amount')
            ->groupBy('type')
            ->toBase()
            ->get()
            ->keyBy('type');

        $invoices = $stats->get(InvoiceType::Invoice->value);
        $credits = $stats->get(InvoiceType::CreditNote->value);

        $latest = Invoice::query()
            ->orderByDesc('issued_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Invoice $invoice) => [
                $invoice->number,
                $invoice->type->prefix(),
                $invoice->customer_name,
                $this->money($invoice->total_amount),
                $invoice->issued_at->format('d/m/Y'),
            ])
            ->all();

        return Widgets::section(
            'Facturation',
            'Invoicing',
            '/factures',
            [
                Widgets::kpi('invoices_issued', 'Factures émises', 'Invoices issued', (int) ($invoices->documents ?? 0)),
                Widgets::kpi('invoiced_total', 'Montant facturé TTC', 'Invoiced (incl. VAT)', (int) ($invoices->amount ?? 0), Widgets::MONEY),
                Widgets::kpi('credit_notes', 'Avoirs émis', 'Credit notes issued', (int) ($credits->documents ?? 0)),
                Widgets::kpi('credit_notes_total', 'Montant des avoirs', 'Credit notes amount', (int) ($credits->amount ?? 0), Widgets::MONEY),
            ],
            [
                Widgets::chart('invoiced_by_day', 'Montant facturé par jour', 'Invoiced per day', 'bar', $this->byDay($from, $to), Widgets::MONEY),
            ],
            [
                Widgets::table('latest_documents', 'Derniers documents', 'Latest documents', [
                    ['N°', 'No.'], ['Type', 'Type'], ['Client', 'Customer'], ['Montant', 'Amount'], ['Date', 'Date'],
                ], $latest),
            ],
        );
    }

    /**
     * @return list<array{label: string, value: int}>
     */
    private function byDay(CarbonInterface $from, CarbonInterface $to): array
    {
        $start = CarbonImmutable::instance($from)->startOfDay();
        $days = max(1, (int) $start->diffInDays(CarbonImmutable::instance($to)->startOfDay()) + 1);
        $step = (int) ceil($days / self::MAX_POINTS);

        $perDay = Invoice::query()
            ->where('type', InvoiceType::Invoice->value)
            ->whereBetween('issued_at', [$from, $to])
            ->selectRaw('DATE(issued_at) as day, sum(total_amount) as amount')
            ->groupBy('day')
            ->toBase()
            ->pluck('amount', 'day');

        $points = [];

        for ($offset = 0; $offset < $days; $offset += $step) {
            $total = 0;

            for ($i = $offset; $i < min($offset + $step, $days); $i++) {
                $total += (int) ($perDay[$start->addDays($i)->toDateString()] ?? 0);
            }

            $points[] = ['label' => $start->addDays($offset)->format('d/m'), 'value' => $total];
        }

        return $points;
    }

    private function money(int $amount): string
    {
        return number_format($amount, 0, ',', ' ').' XOF';
    }
}
