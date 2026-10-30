<?php

declare(strict_types=1);

namespace App\Modules\Payments\Application\Dashboard;

use App\Modules\Payments\Domain\PaymentMethod;
use App\Modules\Payments\Domain\PaymentStatus;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Payments\Infrastructure\Models\PaymentRefund;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

final class PaymentsSection implements DashboardSection
{
    /** @var array<string, array{0: string, 1: string}> */
    private const METHOD_LABELS = [
        'cod' => ['Paiement à la livraison', 'Cash on delivery'],
        'mobile_money' => ['Mobile money', 'Mobile money'],
        'cash' => ['Espèces (caisse)', 'Cash (till)'],
    ];

    /** @var array<string, array{0: string, 1: string}> */
    private const STATUS_LABELS = [
        'pending' => ['En attente', 'Pending'],
        'completed' => ['Encaissé', 'Collected'],
        'failed' => ['Échec', 'Failed'],
        'refunded' => ['Remboursé', 'Refunded'],
    ];

    public function key(): string
    {
        return 'payments';
    }

    public function ability(): string
    {
        return 'manage-payments';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $collected = Payment::query()
            ->whereIn('status', [PaymentStatus::Completed, PaymentStatus::Refunded])
            ->whereBetween('paid_at', [$from, $to])
            ->selectRaw('method, SUM(amount_amount) as total')
            ->groupBy('method')
            ->get()
            ->mapWithKeys(fn ($row) => [$row->method->value => (int) $row->total]);

        $refunded = (int) PaymentRefund::query()->whereBetween('created_at', [$from, $to])->sum('amount');

        $awaiting = (int) Payment::query()
            ->where('method', PaymentMethod::Cod)
            ->where('status', PaymentStatus::Pending)
            ->sum('amount_amount');

        $failed = Payment::query()
            ->where('status', PaymentStatus::Failed)
            ->whereBetween('created_at', [$from, $to])
            ->count();

        $points = [];
        foreach ($collected as $method => $total) {
            [$fr, $en] = self::METHOD_LABELS[$method] ?? [$method, $method];
            $points[] = ['label' => $fr, 'label_en' => $en, 'value' => $total];
        }

        $rows = Payment::query()
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get(['order_number', 'method', 'amount_amount', 'status', 'created_at'])
            ->map(fn (Payment $payment) => [
                $payment->order_number,
                self::METHOD_LABELS[$payment->method->value][0] ?? $payment->method->value,
                number_format($payment->amount_amount, 0, ',', ' ').' XOF',
                self::STATUS_LABELS[$payment->status->value][0],
                $payment->created_at?->format('d/m/Y H:i'),
            ])
            ->all();

        return Widgets::section(
            'Paiements',
            'Payments',
            '/paiements',
            [
                Widgets::kpi('collected', 'Encaissé (période)', 'Collected (period)', $collected->sum(), Widgets::MONEY, Widgets::GOOD),
                Widgets::kpi('refunded', 'Remboursé (période)', 'Refunded (period)', $refunded, Widgets::MONEY, $refunded > 0 ? Widgets::WARN : null),
                Widgets::kpi('awaiting', 'En attente d\'encaissement', 'Awaiting collection', $awaiting, Widgets::MONEY, $awaiting > 0 ? Widgets::WARN : null),
                Widgets::kpi('failed', 'Paiements en échec', 'Failed payments', $failed, Widgets::NUMBER, $failed > 0 ? Widgets::BAD : Widgets::GOOD),
            ],
            [Widgets::chart('collected_by_method', 'Encaissé par méthode', 'Collected by method', 'donut', $points, Widgets::MONEY)],
            [Widgets::table(
                'latest',
                'Derniers paiements',
                'Latest payments',
                [['Commande', 'Order'], ['Méthode', 'Method'], ['Montant', 'Amount'], ['Statut', 'Status'], ['Date', 'Date']],
                $rows,
            )],
        );
    }
}
