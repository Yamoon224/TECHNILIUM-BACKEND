<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use Illuminate\Support\Facades\Log;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function mailedMessages(): Illuminate\Support\Collection
{
    return app('mail.manager')->mailer('array')->getSymfonyTransport()->messages();
}

function confirmedOrderWith(?string $email): string
{
    $offer = Offer::factory()->create(['price_amount' => 59_000, 'stock_quantity' => 5]);
    $payload = [
        'items' => [['offer_id' => $offer->id, 'quantity' => 2]],
        'customer_name' => 'Awa Diallo', 'customer_phone' => '0700000001', 'delivery_address' => 'Cocody',
    ] + ($email === null ? [] : ['customer_email' => $email]);

    $number = test()->postJson('/api/v1/orders', $payload)->assertCreated()->json('order_number');

    $staff = User::factory()->create();
    $staff->assignRole(Role::OperateurCommandes->value);
    test()->actingAs($staff, 'sanctum')->postJson("/api/v1/orders/{$number}/transition", ['status' => 'confirmed'])->assertOk();

    return $number;
}

it('mails the customer their invoice as a PDF attachment when the driver is mail', function () {
    config(['invoicing.email_driver' => 'mail']);

    $number = confirmedOrderWith('awa@example.com');

    $invoice = Invoice::query()->where('order_number', $number)->sole();
    $message = mailedMessages()->first(fn ($sent) => $sent->getOriginalMessage()->getTo()[0]->getAddress() === 'awa@example.com'
        && str_contains((string) $sent->getOriginalMessage()->getSubject(), $invoice->number));

    expect($message)->not->toBeNull();
    $mail = $message->getOriginalMessage();
    $attachments = $mail->getAttachments();

    expect($attachments)->toHaveCount(1)
        ->and($attachments[0]->getFilename())->toBe($invoice->number.'.pdf')
        ->and($attachments[0]->getBody())->toStartWith('%PDF-')
        ->and($mail->getTextBody())->toContain($invoice->number)->toContain('Awa Diallo');
});

it('sends nothing by default: the log driver only notes it, with the address masked', function () {
    Log::spy();

    confirmedOrderWith('awa@example.com');

    expect(mailedMessages()->filter(fn ($sent) => $sent->getOriginalMessage()->getAttachments() !== []))->toHaveCount(0);
    Log::shouldHaveReceived('info')->withArgs(fn ($message, $context = []) => $message === 'Envoi de facture simulé' && ($context['recipient'] ?? '') === 'aw*@example.com');
});

it('does not mail an order that has no e-mail address', function () {
    config(['invoicing.email_driver' => 'mail']);

    confirmedOrderWith(null);

    expect(Invoice::query()->count())->toBe(1)
        ->and(mailedMessages()->filter(fn ($sent) => $sent->getOriginalMessage()->getAttachments() !== []))->toHaveCount(0);
});

it('mails a credit note too, when the accountant issues one', function () {
    config(['invoicing.email_driver' => 'mail']);
    $number = confirmedOrderWith('awa@example.com');
    $invoice = Invoice::query()->where('order_number', $number)->sole();

    $accountant = User::factory()->create();
    $accountant->assignRole(Role::Comptable->value);
    $this->actingAs($accountant, 'sanctum')->postJson("/api/v1/admin/invoices/{$invoice->id}/credit-note", ['reason' => 'Erreur'])->assertCreated();

    $credit = Invoice::query()->where('type', 'credit_note')->sole();
    $subjects = mailedMessages()->map(fn ($sent) => (string) $sent->getOriginalMessage()->getSubject())->all();

    expect(collect($subjects)->contains(fn ($subject) => str_contains($subject, $credit->number)))->toBeTrue();
});

it('never blocks issuing the invoice when the mailer fails', function () {
    config(['invoicing.email_driver' => 'mail', 'mail.default' => 'nonexistent']);

    $number = confirmedOrderWith('awa@example.com');

    expect(Invoice::query()->where('order_number', $number)->count())->toBe(1);
});
