<?php

declare(strict_types=1);

namespace App\Modules\Notifications\Application\Dashboard;

use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Models\Notification;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Dashboard block of the notifications feature: delivery health of customer
 * messages (sent in the period, failures and messages still queued).
 */
final class NotificationsSection implements DashboardSection
{
    private const CHANNELS = [
        'sms' => ['SMS', 'SMS'],
        'whatsapp' => ['WhatsApp', 'WhatsApp'],
        'email' => ['E-mail', 'Email'],
    ];

    private const STATUSES = [
        'sent' => ['Envoyées', 'Sent'],
        'pending' => ['En attente', 'Pending'],
        'failed' => ['En échec', 'Failed'],
    ];

    public function key(): string
    {
        return 'notifications';
    }

    public function ability(): string
    {
        return 'manage-notifications';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $sent = Notification::query()
            ->where('status', NotificationStatus::Sent->value)
            ->whereBetween('sent_at', [$from, $to])
            ->count();

        // Failures and queued messages are shown whatever their age: they still need attention.
        $current = Notification::query()
            ->whereIn('status', [NotificationStatus::Failed->value, NotificationStatus::Pending->value])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $failed = (int) ($current[NotificationStatus::Failed->value] ?? 0);
        $pending = (int) ($current[NotificationStatus::Pending->value] ?? 0);

        $byChannel = Notification::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('channel, count(*) as total')
            ->groupBy('channel')
            ->orderBy('channel')
            ->toBase()
            ->get()
            ->map(function ($row) {
                [$label, $labelEn] = self::CHANNELS[$row->channel] ?? [(string) $row->channel, (string) $row->channel];

                return ['label' => $label, 'label_en' => $labelEn, 'value' => (int) $row->total];
            })
            ->all();

        $byStatus = Notification::query()
            ->whereBetween('created_at', [$from, $to])
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->toBase()
            ->pluck('total', 'status');

        $statusPoints = [];

        foreach (self::STATUSES as $status => [$label, $labelEn]) {
            if ((int) ($byStatus[$status] ?? 0) > 0) {
                $statusPoints[] = ['label' => $label, 'label_en' => $labelEn, 'value' => (int) $byStatus[$status]];
            }
        }

        $failures = Notification::query()
            ->where('status', NotificationStatus::Failed->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Notification $notification) => [
                self::CHANNELS[$notification->channel][0] ?? $notification->channel,
                $this->mask($notification->recipient),
                $notification->event,
                Str::limit((string) $notification->error, 60),
            ])
            ->all();

        return Widgets::section(
            'Notifications',
            'Notifications',
            '/notifications',
            [
                Widgets::kpi('sent', 'Envoyées sur la période', 'Sent in the period', $sent),
                Widgets::kpi('failed', 'En échec', 'Failed', $failed, Widgets::NUMBER, $failed > 0 ? Widgets::BAD : null),
                Widgets::kpi('pending', 'En attente / relance', 'Pending / retrying', $pending, Widgets::NUMBER, $pending > 0 ? Widgets::WARN : null),
            ],
            [
                Widgets::chart('by_channel', 'Messages par canal', 'Messages by channel', 'donut', $byChannel),
                Widgets::chart('by_status', 'Messages par statut', 'Messages by status', 'donut', $statusPoints),
            ],
            [
                Widgets::table('recent_failures', 'Derniers échecs', 'Latest failures', [
                    ['Canal', 'Channel'], ['Destinataire', 'Recipient'], ['Événement', 'Event'], ['Erreur', 'Error'],
                ], $failures),
            ],
        );
    }

    /** Keeps the first three and last two characters, enough to recognise a number without exposing it. */
    private function mask(string $recipient): string
    {
        $length = mb_strlen($recipient);

        if ($length <= 5) {
            return str_repeat('*', $length);
        }

        return mb_substr($recipient, 0, 3).str_repeat('*', $length - 5).mb_substr($recipient, -2);
    }
}
