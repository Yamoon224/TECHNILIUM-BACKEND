<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Notifications\Domain\NotificationStatus;
use App\Modules\Notifications\Infrastructure\Models\Notification;
use App\Modules\Orders\Infrastructure\Models\Order;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function checkout(array $extra = []): Illuminate\Testing\TestResponse
{
    $offer = Offer::factory()->create(['price_amount' => 10_000, 'stock_quantity' => 3]);

    return test()->postJson('/api/v1/orders', [
        'items' => [['offer_id' => $offer->id, 'quantity' => 1]],
        'customer_name' => 'Awa Diallo', 'customer_phone' => '0700000001', 'delivery_address' => 'Cocody',
    ] + $extra);
}

it('keeps the e-mail optional: a phone-only checkout still works and sends no e-mail', function () {
    checkout()->assertCreated();

    expect(Notification::query()->pluck('channel')->all())->toBe(['sms'])
        ->and(Order::query()->sole()->customer_email)->toBeNull();
});

it('rejects a malformed e-mail', function () {
    checkout(['customer_email' => 'pas-un-email'])->assertUnprocessable()->assertJsonValidationErrors('customer_email');
});

it('stores the e-mail and also writes to it, next to the SMS', function () {
    $orderNumber = checkout(['customer_email' => 'awa@example.com'])->assertCreated()->json('order_number');

    expect(Order::query()->sole()->customer_email)->toBe('awa@example.com');

    $byChannel = Notification::query()->get()->keyBy('channel');
    expect($byChannel->keys()->sort()->values()->all())->toBe(['email', 'sms'])
        ->and($byChannel['email']->recipient)->toBe('awa@example.com')
        ->and($byChannel['email']->reference)->toBe($orderNumber)
        ->and($byChannel['email']->status)->toBe(NotificationStatus::Sent)
        ->and($byChannel['sms']->recipient)->toBe('0700000001');
});

it('e-mails each later status change too', function () {
    $orderNumber = checkout(['customer_email' => 'awa@example.com'])->json('order_number');
    $staff = User::factory()->create();
    $staff->assignRole(Role::OperateurCommandes->value);

    $this->actingAs($staff, 'sanctum')->postJson("/api/v1/orders/{$orderNumber}/transition", ['status' => 'confirmed'])->assertOk();

    expect(Notification::query()->where('channel', 'email')->orderBy('id')->pluck('event')->all())
        ->toBe(['order.placed', 'order.confirmed']);
});

it('delivers a plain-text mail to the address when the mail driver is on', function () {
    config(['notifications.drivers.email' => 'mail']);

    $orderNumber = checkout(['customer_email' => 'awa@example.com'])->assertCreated()->json('order_number');

    expect(Notification::query()->where('channel', 'email')->sole()->status)->toBe(NotificationStatus::Sent);

    $messages = app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
    expect($messages)->toHaveCount(1);

    $mail = $messages->first()->getOriginalMessage();
    expect($mail->getTo()[0]->getAddress())->toBe('awa@example.com')
        ->and($mail->getTextBody())->toContain($orderNumber);
});

it('sends no real mail while the e-mail channel is on its default log driver', function () {
    checkout(['customer_email' => 'awa@example.com'])->assertCreated();

    expect(app('mail.manager')->mailer('array')->getSymfonyTransport()->messages())->toHaveCount(0);
});

it('records a failed e-mail without breaking the order', function () {
    config(['notifications.drivers.email' => 'mail', 'mail.default' => 'nonexistent']);

    checkout(['customer_email' => 'awa@example.com'])->assertCreated();

    $email = Notification::query()->where('channel', 'email')->sole();
    expect($email->status)->toBe(NotificationStatus::Failed)->and($email->error)->not->toBeEmpty()
        ->and(Order::query()->count())->toBe(1);
});

it('never shows the e-mail to a vendor viewing the order', function () {
    checkout(['customer_email' => 'awa@example.com'])->assertCreated();
    $order = Order::query()->sole();

    $summary = app(App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface::class)->find($order->order_number);
    expect($summary->customerEmail)->toBe('awa@example.com'); // internal contract only

    foreach (app(App\Modules\Orders\Application\Contracts\OrderLookupGatewayInterface::class)->forVendor(999) as $row) {
        expect($row->customerEmail)->toBeNull();
    }
});
