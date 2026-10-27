<?php

declare(strict_types=1);

use App\Modules\Customers\Domain\TicketPriority;
use App\Modules\Customers\Domain\TicketStatus;
use App\Modules\Customers\Infrastructure\Models\Ticket;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Infrastructure\Models\Order;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function customer(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::Client->value);

    return $user;
}

function deskAgent(Role $role = Role::Support): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

function openTicketFor(User $user, array $overrides = [])
{
    return test()->actingAs($user, 'sanctum')->postJson('/api/v1/support/tickets', $overrides + [
        'subject' => 'Colis non reçu',
        'category' => 'delivery',
        'message' => 'Bonjour, je n\'ai rien reçu.',
    ]);
}

it('requires an account to use support', function () {
    $this->getJson('/api/v1/support/tickets')->assertUnauthorized();
    $this->postJson('/api/v1/support/tickets', [])->assertUnauthorized();
});

it('opens a ticket with its first message, always at normal priority', function () {
    $user = customer();

    $response = openTicketFor($user)->assertCreated();

    $response->assertJsonPath('number', fn (string $n) => str_starts_with($n, 'SAV-'))
        ->assertJsonPath('status', 'open')
        ->assertJsonPath('priority', 'normal')
        ->assertJsonCount(1, 'messages')
        ->assertJsonPath('messages.0.from', 'customer');
    expect(Ticket::query()->sole()->customer_id)->toBe($user->id);
});

it('validates a new ticket', function (array $bad) {
    openTicketFor(customer(), $bad)->assertUnprocessable();
})->with([
    'no subject' => [['subject' => '']],
    'bad category' => [['category' => 'nope']],
    'no message' => [['message' => '']],
    'huge message' => [['message' => str_repeat('a', 5001)]],
]);

it('lets a customer attach a ticket to their own order only', function () {
    $mine = customer();
    $other = customer();
    $myOrder = Order::factory()->create(['customer_id' => $mine->id]);
    $theirOrder = Order::factory()->create(['customer_id' => $other->id]);
    $guestOrder = Order::factory()->create(['customer_id' => null]);

    openTicketFor($mine, ['order_number' => $myOrder->order_number])->assertCreated()
        ->assertJsonPath('order_number', $myOrder->order_number);

    foreach ([$theirOrder->order_number, $guestOrder->order_number, 'TCH-NOPE'] as $number) {
        openTicketFor($mine, ['order_number' => $number])->assertUnprocessable();
    }
});

it('shows a customer only their own tickets, and 404s on anyone else\'s', function () {
    $alice = customer();
    $bob = customer();
    $aliceTicket = openTicketFor($alice)->json('id');
    openTicketFor($bob);

    $this->actingAs($alice, 'sanctum')->getJson('/api/v1/support/tickets')
        ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $aliceTicket);

    $this->actingAs($bob, 'sanctum')->getJson("/api/v1/support/tickets/{$aliceTicket}")->assertNotFound();
    $this->actingAs($bob, 'sanctum')->postJson("/api/v1/support/tickets/{$aliceTicket}/messages", ['body' => 'intrus'])->assertNotFound();
    $this->actingAs($bob, 'sanctum')->getJson('/api/v1/support/tickets/999999')->assertNotFound();
});

it('hides internal notes and staff identity from the customer', function () {
    $user = customer();
    $agent = deskAgent();
    $id = openTicketFor($user)->json('id');

    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/admin/tickets/{$id}/messages", ['body' => 'Note : client à risque', 'internal' => true])->assertCreated();
    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/admin/tickets/{$id}/messages", ['body' => 'Nous vérifions.'])->assertCreated();

    $response = $this->actingAs($user, 'sanctum')->getJson("/api/v1/support/tickets/{$id}")->assertOk();

    $response->assertJsonCount(2, 'messages')->assertJsonPath('messages.1.from', 'support');
    expect(json_encode($response->json()))->not->toContain('client à risque')->not->toContain('author_id')->not->toContain('assigned_to');
});

it('moves the ticket like a conversation: staff reply waits on the customer, customer reply reopens', function () {
    $user = customer();
    $agent = deskAgent();
    $id = openTicketFor($user)->json('id');

    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/admin/tickets/{$id}/messages", ['body' => 'Bonjour'])
        ->assertCreated()->assertJsonPath('status', 'pending_customer');

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/support/tickets/{$id}/messages", ['body' => 'Merci'])
        ->assertCreated()->assertJsonPath('status', 'open');
});

it('lets the customer reopen a resolved ticket by replying, but not a closed one', function () {
    $user = customer();
    $agent = deskAgent();
    $id = openTicketFor($user)->json('id');

    $this->actingAs($agent, 'sanctum')->patchJson("/api/v1/admin/tickets/{$id}", ['status' => 'resolved'])->assertOk();
    expect(Ticket::query()->find($id)->resolved_at)->not->toBeNull();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/support/tickets/{$id}/messages", ['body' => 'Toujours pas réglé'])
        ->assertCreated()->assertJsonPath('status', 'open');
    expect(Ticket::query()->find($id)->resolved_at)->toBeNull();

    $this->actingAs($agent, 'sanctum')->patchJson("/api/v1/admin/tickets/{$id}", ['status' => 'resolved'])->assertOk();
    $this->actingAs($agent, 'sanctum')->patchJson("/api/v1/admin/tickets/{$id}", ['status' => 'closed'])->assertOk();

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/support/tickets/{$id}/messages", ['body' => 'Encore ?'])->assertUnprocessable();
    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/admin/tickets/{$id}/messages", ['body' => 'Encore ?'])->assertUnprocessable();
});

it('lets an internal note change nothing, and stops the SLA clock only on the first real reply', function () {
    $id = openTicketFor(customer())->json('id');
    $agent = deskAgent();

    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/admin/tickets/{$id}/messages", ['body' => 'note', 'internal' => true])
        ->assertCreated()->assertJsonPath('status', 'open');
    expect(Ticket::query()->find($id)->first_responded_at)->toBeNull();

    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/admin/tickets/{$id}/messages", ['body' => 'Réponse'])->assertCreated();
    $first = Ticket::query()->find($id)->first_responded_at;
    expect($first)->not->toBeNull();

    // A later staff reply does not move the first-response time.
    $this->travel(2)->hours();
    $this->actingAs($agent, 'sanctum')->postJson("/api/v1/admin/tickets/{$id}/messages", ['body' => 'Suite'])->assertCreated();
    expect(Ticket::query()->find($id)->first_responded_at->equalTo($first))->toBeTrue();
});

it('restricts the support desk to support and administrators', function () {
    $this->getJson('/api/v1/admin/tickets')->assertUnauthorized();

    foreach ([Role::Client, Role::Comptable, Role::Marketing, Role::VendeurTiers] as $role) {
        $this->actingAs(deskAgent($role), 'sanctum')->getJson('/api/v1/admin/tickets')->assertForbidden();
    }

    $this->actingAs(deskAgent(Role::Administrateur), 'sanctum')->getJson('/api/v1/admin/tickets')->assertOk();
});

it('refuses undeclared status transitions and unknown values', function () {
    $id = openTicketFor(customer())->json('id');
    $agent = deskAgent();

    $this->actingAs($agent, 'sanctum')->patchJson("/api/v1/admin/tickets/{$id}", ['status' => 'closed'])->assertUnprocessable();
    $this->actingAs($agent, 'sanctum')->patchJson("/api/v1/admin/tickets/{$id}", ['status' => 'banana'])->assertUnprocessable();
});

it('changes priority and assignment, and releases a ticket', function () {
    $id = openTicketFor(customer())->json('id');
    $agent = deskAgent();

    $this->actingAs($agent, 'sanctum')->patchJson("/api/v1/admin/tickets/{$id}", ['priority' => 'high', 'assigned_to_me' => true])
        ->assertOk()->assertJsonPath('priority', 'high')->assertJsonPath('assigned_to', $agent->id);

    $this->actingAs($agent, 'sanctum')->patchJson("/api/v1/admin/tickets/{$id}", ['assigned_to_me' => false])
        ->assertOk()->assertJsonPath('assigned_to', null);
});

it('filters the queue by status, priority, assignee, customer and text', function () {
    $agent = deskAgent();
    $a = Ticket::factory()->create(['subject' => 'Écran cassé', 'assigned_to' => $agent->id, 'customer_id' => 11]);
    Ticket::factory()->priority(TicketPriority::High)->create(['subject' => 'Paiement double', 'customer_id' => 12]);
    Ticket::factory()->status(TicketStatus::Resolved)->create(['subject' => 'Ancien', 'customer_id' => 12]);

    $get = fn (string $q) => $this->actingAs($agent, 'sanctum')->getJson("/api/v1/admin/tickets?{$q}");

    $get('status=resolved')->assertJsonCount(1, 'data');
    $get('priority=high')->assertJsonCount(1, 'data');
    $get('assigned=me')->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $a->id);
    $get('assigned=none')->assertJsonCount(2, 'data');
    $get('customer_id=12')->assertJsonCount(2, 'data');
    $get('q=cassé')->assertJsonCount(1, 'data');
    $get('q=%25')->assertJsonCount(0, 'data'); // a literal percent, not a wildcard
});

it('flags and filters tickets past their first-response target', function () {
    $agent = deskAgent();
    $late = Ticket::factory()->create(['subject' => 'En retard']);
    $late->forceFill(['created_at' => now()->subHours(9)])->save(); // normal = 8 h
    Ticket::factory()->create(['subject' => 'Récent']);
    $answered = Ticket::factory()->create(['subject' => 'Répondu', 'first_responded_at' => now()]);
    $answered->forceFill(['created_at' => now()->subDays(3)])->save();

    $response = $this->actingAs($agent, 'sanctum')->getJson('/api/v1/admin/tickets?overdue=1')->assertOk();

    $response->assertJsonCount(1, 'data')->assertJsonPath('data.0.subject', 'En retard')->assertJsonPath('data.0.overdue', true);
    $this->actingAs($agent, 'sanctum')->getJson('/api/v1/admin/tickets')->assertJsonCount(3, 'data');
});

it('orders the queue with the waiting tickets first', function () {
    $agent = deskAgent();
    Ticket::factory()->status(TicketStatus::Resolved)->create(['subject' => 'C']);
    Ticket::factory()->status(TicketStatus::PendingCustomer)->create(['subject' => 'B']);
    Ticket::factory()->create(['subject' => 'A']);

    $this->actingAs($agent, 'sanctum')->getJson('/api/v1/admin/tickets')
        ->assertJsonPath('data.0.subject', 'A')
        ->assertJsonPath('data.1.subject', 'B')
        ->assertJsonPath('data.2.subject', 'C');
});
