<?php

declare(strict_types=1);

use App\Modules\Accounting\Infrastructure\Models\JournalEntry;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Payments\Infrastructure\Models\Payment;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function treasurer(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Comptable->value);

    return $user;
}

it('refunds part of a payment, keeping it completed with the rest refundable', function () {
    $payment = Payment::factory()->completed()->create(['amount_amount' => 100_000]);

    $this->actingAs(treasurer(), 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => 40_000, 'reason' => 'Geste'])
        ->assertOk()
        ->assertJsonPath('status', 'completed')
        ->assertJsonPath('refunded_amount', 40_000)
        ->assertJsonPath('refundable_amount', 60_000);
});

it('turns the payment refunded once fully given back, across several refunds', function () {
    $payment = Payment::factory()->completed()->create(['amount_amount' => 100_000]);
    $user = treasurer();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => 40_000])->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/refund")
        ->assertOk()
        ->assertJsonPath('status', 'refunded')
        ->assertJsonPath('refunded_amount', 100_000)
        ->assertJsonPath('refundable_amount', 0);

    // Two refunds, two separate ledger entries, each reversing the right amount.
    expect(JournalEntry::query()->where('source_type', 'payment_refund')->count())->toBe(2);
});

it('refuses to refund more than what remains, or an uncollected payment', function () {
    $user = treasurer();
    $payment = Payment::factory()->completed()->create(['amount_amount' => 100_000]);
    $pending = Payment::factory()->create();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => 100_001])->assertUnprocessable();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => 0])->assertUnprocessable();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$pending->id}/refund")->assertUnprocessable();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => 70_000])->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => 30_001])->assertUnprocessable();
});

it('refuses any refund of a fully refunded payment', function () {
    $payment = Payment::factory()->completed()->create(['amount_amount' => 10_000]);
    $user = treasurer();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/refund")->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/refund", ['amount' => 1])->assertUnprocessable();
});
