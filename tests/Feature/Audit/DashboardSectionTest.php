<?php

declare(strict_types=1);

use App\Modules\Audit\Application\Dashboard\AuditSection;
use App\Modules\Audit\Infrastructure\Models\AuditLog;
use App\Modules\Identity\Domain\AccessCatalogue;
use Carbon\CarbonImmutable;

function auditKpi(array $section, string $key): array
{
    return collect($section['kpis'])->firstWhere('key', $key);
}

function auditEntry(int $n, array $attributes = []): AuditLog
{
    return AuditLog::query()->create($attributes + [
        'user_id' => 1,
        'user_name' => 'Awa',
        'method' => 'POST',
        'path' => 'api/v1/admin/orders',
        'status' => 200,
        'payload' => ['password' => 'secret'],
        'occurred_at' => CarbonImmutable::now(),
        'previous_hash' => str_repeat('0', 64),
        'hash' => hash('sha256', 'entry-'.$n),
    ]);
}

it('declares a known ability and a key', function () {
    $section = app(AuditSection::class);

    expect($section->key())->toBe('audit')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises the audited activity without leaking payloads', function () {
    $now = CarbonImmutable::now();

    auditEntry(1);
    auditEntry(2, ['user_id' => 2, 'user_name' => 'Moussa', 'status' => 403, 'path' => 'api/v1/admin/orders/5?token=abc']);
    auditEntry(3, ['occurred_at' => $now->subDays(2)]);
    auditEntry(4, ['occurred_at' => $now->subDays(40)]);

    $section = app(AuditSection::class)->build($now->subDays(6)->startOfDay(), $now->endOfDay());

    expect($section['link'])->toBe('/audit')
        ->and(auditKpi($section, 'actions')['value'])->toBe(3)
        ->and(auditKpi($section, 'actors')['value'])->toBe(2)
        ->and(auditKpi($section, 'failures')['value'])->toBe(1);

    $points = $section['charts'][0]['points'];
    expect($points)->toHaveCount(7)
        ->and($points[6]['value'])->toBe(2)
        ->and($points[4]['value'])->toBe(1);

    $rows = $section['tables'][0]['rows'];
    expect($rows)->toHaveCount(4)
        ->and($rows[0][2])->toContain('POST api/v1/admin/orders')
        ->and(json_encode($section))->not->toContain('secret')
        ->and(json_encode($section))->not->toContain('token=abc');
});

it('copes with an empty database', function () {
    $now = CarbonImmutable::now();
    $section = app(AuditSection::class)->build($now->subDays(6)->startOfDay(), $now->endOfDay());

    expect(auditKpi($section, 'actions')['value'])->toBe(0)
        ->and(auditKpi($section, 'actors')['value'])->toBe(0)
        ->and(auditKpi($section, 'failures')['value'])->toBe(0)
        ->and($section['charts'][0]['points'])->toHaveCount(7)
        ->and($section['tables'][0]['rows'])->toBe([]);
});
