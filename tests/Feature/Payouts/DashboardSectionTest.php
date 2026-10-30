<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\AccessCatalogue;
use App\Modules\Payouts\Application\Dashboard\PayoutsSection;
use App\Modules\Payouts\Domain\PayoutStatus;
use App\Modules\Payouts\Infrastructure\Models\Payout;
use Carbon\CarbonImmutable;

function payoutsKpi(array $section, string $key): array
{
    return collect($section['kpis'])->firstWhere('key', $key);
}

it('declares a known ability and a key', function () {
    $section = app(PayoutsSection::class);

    expect($section->key())->toBe('payouts')
        ->and(AccessCatalogue::keys())->toContain($section->ability());
});

it('summarises what is owed, earned and paid', function () {
    $today = CarbonImmutable::now();

    Payout::factory()->create(['vendor_name' => 'Boutique A', 'net_amount' => 90_000, 'commission_amount' => 10_000]);
    Payout::factory()->create(['vendor_name' => 'Boutique B', 'net_amount' => 45_000, 'commission_amount' => 5_000]);
    Payout::factory()->create([
        'status' => PayoutStatus::Paid, 'paid_at' => $today, 'net_amount' => 27_000, 'commission_amount' => 3_000,
    ]);

    $section = app(PayoutsSection::class)->build($today->subDays(6)->startOfDay(), $today->endOfDay());

    expect($section['link'])->toBe('/reversements')
        ->and(payoutsKpi($section, 'owed')['value'])->toBe(135_000)
        ->and(payoutsKpi($section, 'owed')['tone'])->toBe('warn')
        ->and(payoutsKpi($section, 'owed')['format'])->toBe('money')
        ->and(payoutsKpi($section, 'commissions')['value'])->toBe(18_000)
        ->and(payoutsKpi($section, 'paid')['value'])->toBe(27_000)
        ->and(payoutsKpi($section, 'pending')['value'])->toBe(2);

    $rows = $section['tables'][0]['rows'];
    expect($rows)->toHaveCount(2)
        ->and($rows[0][0])->toBe('Boutique A')
        ->and($rows[0][3])->toBe('En attente');
});

it('copes with an empty database', function () {
    $today = CarbonImmutable::now();
    $section = app(PayoutsSection::class)->build($today->subDays(6)->startOfDay(), $today->endOfDay());

    expect(payoutsKpi($section, 'owed')['value'])->toBe(0)
        ->and(payoutsKpi($section, 'owed')['tone'])->toBeNull()
        ->and(payoutsKpi($section, 'pending')['value'])->toBe(0)
        ->and($section['tables'][0]['rows'])->toBe([]);
});
