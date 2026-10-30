<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Notifications\Application\Dashboard\NotificationsSection;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Models\Notification;
use Carbon\CarbonImmutable;

function notificationsKpi(array $section, string $key): array
{
    return collect($section['kpis'])->firstWhere('key', $key);
}

function notificationRow(string $key, array $attributes = []): Notification
{
    return Notification::factory()->create(['dedupe_key' => $key] + $attributes);
}

it('declares a known ability and a key', function () {
    $section = app(NotificationsSection::class);

    expect($section->key())->toBe('notifications')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises delivery health', function () {
    $now = CarbonImmutable::now();

    notificationRow('a');
    notificationRow('b', ['channel' => 'whatsapp']);
    notificationRow('c', ['status' => NotificationStatus::Pending, 'sent_at' => null]);
    Notification::factory()->failed()->create([
        'dedupe_key' => 'd', 'recipient' => '0701020304', 'error' => 'Timeout opérateur', 'event' => 'order.shipped',
    ]);

    $section = app(NotificationsSection::class)->build($now->subDays(6)->startOfDay(), $now->endOfDay());

    expect($section['link'])->toBe('/notifications')
        ->and(notificationsKpi($section, 'sent')['value'])->toBe(2)
        ->and(notificationsKpi($section, 'failed')['value'])->toBe(1)
        ->and(notificationsKpi($section, 'failed')['tone'])->toBe('bad')
        ->and(notificationsKpi($section, 'pending')['value'])->toBe(1);

    $channels = collect($section['charts'][0]['points'])->pluck('value', 'label');
    expect($channels['SMS'])->toBe(3)->and($channels['WhatsApp'])->toBe(1);

    $row = $section['tables'][0]['rows'][0];
    expect($section['tables'][0]['rows'])->toHaveCount(1)
        ->and($row[0])->toBe('SMS')
        ->and($row[1])->toBe('070*****04')
        ->and($row[2])->toBe('order.shipped')
        ->and($row[3])->toBe('Timeout opérateur');
});

it('copes with an empty database', function () {
    $now = CarbonImmutable::now();
    $section = app(NotificationsSection::class)->build($now->subDays(6)->startOfDay(), $now->endOfDay());

    expect(notificationsKpi($section, 'sent')['value'])->toBe(0)
        ->and(notificationsKpi($section, 'failed')['tone'])->toBeNull()
        ->and($section['charts'][0]['points'])->toBe([])
        ->and($section['tables'][0]['rows'])->toBe([]);
});
