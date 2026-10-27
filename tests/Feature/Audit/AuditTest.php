<?php

declare(strict_types=1);

use App\Modules\Audit\Application\RecordAuditEntryAction;
use App\Modules\Audit\Domain\AuditChain;
use App\Modules\Audit\Domain\Exceptions\AuditException;
use App\Modules\Audit\Infrastructure\Models\AuditLog;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Shipping\Infrastructure\Models\Shipment;
use Illuminate\Support\Facades\DB;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function staffAs(Role $role): User
{
    $user = User::factory()->create(['name' => 'Fatou Comptable']);
    $user->assignRole($role->value);

    return $user;
}

it('records who did what, when and with what outcome, for a back-office mutation', function () {
    $payment = Payment::factory()->create();
    $user = staffAs(Role::Comptable);

    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/confirm")
        ->assertOk();

    $entry = AuditLog::query()->sole();

    expect($entry->user_id)->toBe($user->id)
        ->and($entry->user_name)->toBe('Fatou Comptable')
        ->and($entry->roles)->toBe('comptable')
        ->and($entry->method)->toBe('POST')
        ->and($entry->path)->toBe("/api/v1/admin/payments/{$payment->id}/confirm")
        ->and($entry->route)->toBe('api/v1/admin/payments/{payment}/confirm')
        ->and($entry->status)->toBe(200)
        ->and($entry->ip)->not->toBeNull()
        ->and($entry->occurred_at)->not->toBeNull();
});

it('records the request body with secrets masked', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Shipped, 'delivery_code' => '482913']);
    $shipment = Shipment::factory()->inTransit()->create(['order_number' => $order->order_number]);

    $this->actingAs(staffAs(Role::Logistique), 'sanctum')
        ->postJson("/api/v1/admin/shipments/{$shipment->id}/deliver", ['confirmation_code' => '482913'])
        ->assertOk();

    $entry = AuditLog::query()->sole();
    expect($entry->payload)->toBe(['confirmation_code' => '[masqué]'])
        ->and(json_encode($entry->payload))->not->toContain('482913');
});

it('records refused attempts too, with their status', function () {
    $payment = Payment::factory()->create();

    $this->actingAs(staffAs(Role::Support), 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/confirm")
        ->assertForbidden();

    expect(AuditLog::query()->sole()->status)->toBe(403);
});

it('does not record reads, customers or guests', function () {
    $this->actingAs(staffAs(Role::Comptable), 'sanctum')->getJson('/api/v1/admin/payments')->assertOk();

    $customer = User::factory()->create();
    $customer->assignRole(Role::Client->value);
    $this->actingAs($customer, 'sanctum')->postJson('/api/v1/orders', ['items' => []]); // 422, but a customer

    $this->postJson('/api/v1/orders', ['items' => []]); // guest

    expect(AuditLog::query()->count())->toBe(0);
});

it('chains entries: each one signs the previous', function () {
    $user = staffAs(Role::Comptable);
    foreach (range(1, 3) as $_) {
        $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/payments/999999/confirm'); // 404, still recorded
    }

    [$first, $second, $third] = AuditLog::query()->orderBy('id')->get()->all();

    expect($first->previous_hash)->toBe(AuditChain::GENESIS)
        ->and($second->previous_hash)->toBe($first->hash)
        ->and($third->previous_hash)->toBe($second->hash);
});

it('verifies an intact chain, and an empty one', function () {
    $admin = staffAs(Role::Administrateur);

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/audit/verify')
        ->assertOk()->assertJsonPath('valid', true)->assertJsonPath('checked', 0);

    foreach (range(1, 4) as $_) {
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/payments/999999/confirm');
    }

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/audit/verify')
        ->assertOk()->assertJsonPath('valid', true)->assertJsonPath('checked', 4);
});

it('detects an entry edited directly in the database', function () {
    $admin = staffAs(Role::Administrateur);
    foreach (range(1, 3) as $_) {
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/payments/999999/confirm');
    }
    $target = AuditLog::query()->orderBy('id')->skip(1)->first();

    DB::table('audit_logs')->where('id', $target->id)->update(['status' => 200, 'user_name' => 'Quelqu\'un d\'autre']);

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/audit/verify')
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('broken_at', $target->id)
        ->assertJsonPath('reason', fn (string $r) => str_contains($r, 'modifié'));
});

it('detects a deleted entry as a broken link', function () {
    $admin = staffAs(Role::Administrateur);
    foreach (range(1, 4) as $_) {
        $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/payments/999999/confirm');
    }
    $entries = AuditLog::query()->orderBy('id')->get();

    DB::table('audit_logs')->where('id', $entries[1]->id)->delete();

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/audit/verify')
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('broken_at', $entries[2]->id);
});

it('cannot be forged without the application key', function () {
    $admin = staffAs(Role::Administrateur);
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/payments/999999/confirm');
    $entry = AuditLog::query()->sole();

    // An attacker rewrites the row AND recomputes a signature with a guessed key.
    DB::table('audit_logs')->where('id', $entry->id)->update(['status' => 200]);
    $forged = AuditLog::query()->find($entry->id);
    DB::table('audit_logs')->where('id', $entry->id)->update([
        'hash' => AuditChain::sign($forged->signedContent(), AuditChain::GENESIS, 'a-guessed-key'),
    ]);

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/audit/verify')
        ->assertJsonPath('valid', false);
});

it('refuses to update or delete an entry through the application', function () {
    $entry = app(RecordAuditEntryAction::class)->execute([
        'method' => 'POST', 'path' => '/x', 'status' => 200,
    ]);

    expect(fn () => $entry->update(['status' => 500]))->toThrow(AuditException::class);
    expect(fn () => $entry->delete())->toThrow(AuditException::class);
});

it('never breaks the request when auditing itself fails', function () {
    $payment = Payment::factory()->create();
    $this->app->bind(RecordAuditEntryAction::class, fn () => throw new RuntimeException('audit store down'));

    $this->actingAs(staffAs(Role::Comptable), 'sanctum')
        ->postJson("/api/v1/admin/payments/{$payment->id}/confirm")
        ->assertOk();

    expect($payment->fresh()->status->value)->toBe('completed');
});

it('restricts the trail to administrators', function () {
    $this->getJson('/api/v1/admin/audit')->assertUnauthorized();

    foreach ([Role::Comptable, Role::Support, Role::Marketing, Role::VendeurTiers] as $role) {
        $this->actingAs(staffAs($role), 'sanctum')->getJson('/api/v1/admin/audit')->assertForbidden();
        $this->actingAs(staffAs($role), 'sanctum')->getJson('/api/v1/admin/audit/verify')->assertForbidden();
    }

    $this->actingAs(staffAs(Role::Administrateur), 'sanctum')->getJson('/api/v1/admin/audit')->assertOk();
});

it('filters the trail by user, method, path, outcome and period', function () {
    $alice = staffAs(Role::Comptable);
    $bob = staffAs(Role::Support);
    $admin = staffAs(Role::Administrateur);
    $payment = Payment::factory()->create();

    $this->actingAs($alice, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/confirm")->assertOk();
    $this->actingAs($bob, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/refund")->assertForbidden();
    $this->actingAs($alice, 'sanctum')->deleteJson('/api/v1/vendors/me/offers/1');

    $get = fn (string $q) => $this->actingAs($admin, 'sanctum')->getJson("/api/v1/admin/audit?{$q}");

    $get("user_id={$bob->id}")->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.status', 403);
    $get('failed=1')->assertOk()->assertJsonCount(2, 'data'); // the 403 and the vendor-route 403
    $get('method=DELETE')->assertOk()->assertJsonCount(1, 'data');
    $get('path=refund')->assertOk()->assertJsonCount(1, 'data');
    $get('from='.now()->toDateString().'&to='.now()->toDateString())->assertOk()->assertJsonCount(3, 'data');
    $get('to=2000-01-01')->assertOk()->assertJsonCount(0, 'data');
});

it('treats a percent sign in the path filter as a literal', function () {
    $admin = staffAs(Role::Administrateur);
    $this->actingAs($admin, 'sanctum')->postJson('/api/v1/admin/payments/999999/confirm');

    $this->actingAs($admin, 'sanctum')->getJson('/api/v1/admin/audit?path=%25')
        ->assertOk()->assertJsonCount(0, 'data');
});

it('rejects an invalid filter', function () {
    $this->actingAs(staffAs(Role::Administrateur), 'sanctum')
        ->getJson('/api/v1/admin/audit?method=GET&from=31/10/2026')
        ->assertUnprocessable();
});
