<?php

declare(strict_types=1);

use App\Modules\Accounting\Application\Actions\PostJournalEntryAction;
use App\Modules\Accounting\Domain\EntryLine;
use App\Modules\Accounting\Domain\Exceptions\AccountingException;
use App\Modules\Accounting\Infrastructure\Models\JournalEntry;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Infrastructure\Models\Payment;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function accountant(Role $role = Role::Comptable): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

it('restricts the ledger to finance roles', function () {
    $this->getJson('/api/v1/admin/accounting/entries')->assertUnauthorized();
    $this->actingAs(accountant(Role::Support), 'sanctum')
        ->getJson('/api/v1/admin/accounting/trial-balance')
        ->assertForbidden();
});

it('posts a balanced sales entry when an invoice is issued', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Confirmed, 'subtotal_amount' => 118_000]);

    $this->actingAs(accountant(), 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => $order->order_number])
        ->assertCreated();

    $entry = JournalEntry::query()->with('lines')->where('reference', $order->order_number)->sole();

    expect($entry->journal)->toBe('VT')
        ->and($entry->lines->sum('debit'))->toBe(118_000)
        ->and($entry->lines->sum('credit'))->toBe(118_000)
        ->and($entry->lines->firstWhere('account_code', '4431')->credit)->toBe(18_000);
});

it('posts a mirrored entry for a credit note, netting the sale to zero', function () {
    $order = Order::factory()->create(['status' => OrderStatus::Confirmed, 'subtotal_amount' => 118_000]);
    $user = accountant();

    $invoice = $this->actingAs($user, 'sanctum')
        ->postJson('/api/v1/admin/invoices', ['order_number' => $order->order_number])->json();
    $this->actingAs($user, 'sanctum')
        ->postJson("/api/v1/admin/invoices/{$invoice['id']}/credit-note", ['reason' => 'Erreur']);

    $balance = $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/accounting/trial-balance')->json();
    $byCode = collect($balance['accounts'])->keyBy('code');

    expect($byCode['411']['balance'])->toBe(0)
        ->and($byCode['701']['balance'])->toBe(0)
        ->and($byCode['4431']['balance'])->toBe(0);
});

it('posts cash and clears the receivable when a payment is confirmed, and reverses on refund', function () {
    $payment = Payment::factory()->create(['amount_amount' => 50_000]);
    $user = accountant();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/confirm")->assertOk();

    $entry = JournalEntry::query()->with('lines')->where('source_type', 'payment')->sole();
    expect($entry->lines->firstWhere('account_code', '411')->credit)->toBe(50_000);

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/refund")->assertOk();

    expect(JournalEntry::query()->where('source_type', 'payment_refund')->count())->toBe(1);

    $balance = $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/accounting/trial-balance')->json();
    expect($balance['total_debit'])->toBe($balance['total_credit']);
    expect(collect($balance['accounts'])->firstWhere('code', '411')['balance'])->toBe(0);
});

it('does not post a payment that fails', function () {
    $payment = Payment::factory()->create();

    $this->actingAs(accountant(), 'sanctum')->postJson("/api/v1/admin/payments/{$payment->id}/fail")->assertOk();

    expect(JournalEntry::query()->count())->toBe(0);
});

it('refuses an unbalanced entry', function () {
    expect(fn () => app(PostJournalEntryAction::class)->execute(
        'VT', 'test', 1, 'x', [new EntryLine('411', debit: 100), new EntryLine('701', credit: 90)], 'XOF',
    ))->toThrow(AccountingException::class);
});

it('is idempotent per source: replaying posts nothing new', function () {
    $post = app(PostJournalEntryAction::class);
    $lines = [new EntryLine('411', debit: 100), new EntryLine('701', credit: 100)];

    $first = $post->execute('VT', 'test', 7, 'x', $lines, 'XOF');
    $second = $post->execute('VT', 'test', 7, 'x', $lines, 'XOF');

    expect($second->id)->toBe($first->id)->and(JournalEntry::query()->count())->toBe(1);
});

it('keeps the ledger append-only', function () {
    $entry = app(PostJournalEntryAction::class)->execute(
        'VT', 'test', 1, 'x', [new EntryLine('411', debit: 100), new EntryLine('701', credit: 100)], 'XOF',
    );

    expect(fn () => $entry->update(['description' => 'y']))->toThrow(AccountingException::class);
    expect(fn () => $entry->delete())->toThrow(AccountingException::class);
    expect(fn () => $entry->lines()->first()->delete())->toThrow(AccountingException::class);
});

it('lists entries filtered by reference', function () {
    $post = app(PostJournalEntryAction::class);
    $lines = [new EntryLine('411', debit: 100), new EntryLine('701', credit: 100)];
    $post->execute('VT', 'test', 1, 'a', $lines, 'XOF', 'REF-A');
    $post->execute('VT', 'test', 2, 'b', $lines, 'XOF', 'REF-B');

    $this->actingAs(accountant(), 'sanctum')
        ->getJson('/api/v1/admin/accounting/entries?reference=REF-A')
        ->assertOk()
        ->assertJsonCount(1, 'data');
});
