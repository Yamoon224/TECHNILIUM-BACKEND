<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Payments\Infrastructure\Models\Payment;
use App\Modules\Sales\Infrastructure\Models\CashClosing;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function cashier(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::OperateurCommandes->value);

    return $user;
}

function ring(User $user, int $price = 50_000, int $quantity = 2, string $method = 'cash', int $stock = 5): array
{
    $offer = Offer::factory()->create(['price_amount' => $price, 'stock_quantity' => $stock, 'status' => OfferStatus::Published, 'vendor_id' => null]);
    $body = ['items' => [['offer_id' => $offer->id, 'quantity' => $quantity]], 'method' => $method];
    if ($method === 'mobile_money') {
        $body += ['provider' => 'wave'];
    }

    $number = test()->actingAs($user, 'sanctum')->postJson('/api/v1/admin/internal-sales', $body)->assertCreated()->json('order_number');

    return [$number, $offer];
}

it('prints a thermal ticket on request', function () {
    $user = cashier();
    [$number] = ring($user);

    $ticket = $this->actingAs($user, 'sanctum')->get("/api/v1/admin/internal-sales/{$number}/receipt?format=ticket")->assertOk();
    $payment = Payment::query()->where('order_number', $number)->firstOrFail();

    expect($ticket->getContent())->toStartWith('%PDF-')
        ->and($ticket->headers->get('content-disposition'))->toContain(sprintf('REC-%06d-ticket.pdf', $payment->id));

    $html = app(App\Modules\Payments\Application\RenderPaymentReceipt::class)->html($payment, 'fr', 'ticket');
    expect($html)->toContain('data:image/png;base64,')->toContain('Merci pour votre achat');
});

it('lets the staff payments receipt be a ticket too, A5 by default', function () {
    $user = User::factory()->create();
    $user->assignRole(Role::Comptable->value);
    $payment = Payment::factory()->completed()->create(['order_number' => Order::factory()->create()->order_number]);

    $this->actingAs($user, 'sanctum')->get("/api/v1/admin/payments/{$payment->id}/receipt?format=ticket")->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename="'.sprintf('REC-%06d-ticket.pdf', $payment->id).'"');
    $this->actingAs($user, 'sanctum')->get("/api/v1/admin/payments/{$payment->id}/receipt")->assertOk()
        ->assertHeader('content-disposition', 'attachment; filename="'.sprintf('REC-%06d.pdf', $payment->id).'"');
});

it('cancels a sale: stock back, credit note, full refund, record kept', function () {
    $user = cashier();
    [$number, $offer] = ring($user);
    expect($offer->fresh()->stock_quantity)->toBe(3);

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/internal-sales/{$number}/cancel", ['reason' => 'Erreur de saisie'])
        ->assertOk()->assertJsonPath('cancel_reason', 'Erreur de saisie')->assertJsonPath('payment.method', 'cash');

    $payment = Payment::query()->where('order_number', $number)->firstOrFail();
    expect($offer->fresh()->stock_quantity)->toBe(5)
        ->and($payment->status->value)->toBe('refunded')->and($payment->refunded_amount)->toBe(100_000)
        ->and(Invoice::query()->where('order_number', $number)->count())->toBe(2)
        ->and(Order::query()->where('order_number', $number)->firstOrFail()->status->value)->toBe('delivered');

    $list = $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/internal-sales')->assertOk();
    expect($list->json('data.0.cancelled_at'))->not->toBeNull();
});

it('refuses a second cancellation, a missing reason and an unknown sale', function () {
    $user = cashier();
    [$number] = ring($user);

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/internal-sales/{$number}/cancel", [])->assertUnprocessable();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/internal-sales/{$number}/cancel", ['reason' => 'Client repenti'])->assertOk();
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/internal-sales/{$number}/cancel", ['reason' => 'Encore'])->assertUnprocessable();
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/internal-sales/TCH-NOPE/cancel', ['reason' => 'Inconnue'])->assertNotFound();
});

it('refuses to cancel once the return window has passed', function () {
    $user = cashier();
    [$number] = ring($user);
    Order::query()->where('order_number', $number)->update(['delivered_at' => now()->subDays(60)]);

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/internal-sales/{$number}/cancel", ['reason' => 'Trop tard'])->assertUnprocessable();
    expect(Payment::query()->where('order_number', $number)->firstOrFail()->status->value)->toBe('completed');
});

it('restricts cancellation and closing to the sales roles', function () {
    $user = cashier();
    [$number] = ring($user);
    $outsider = User::factory()->create();
    $outsider->assignRole(Role::Support->value);

    $this->app['auth']->forgetGuards();
    $this->postJson("/api/v1/admin/internal-sales/{$number}/cancel", ['reason' => 'Test'])->assertUnauthorized();
    $this->actingAs($outsider, 'sanctum')->postJson("/api/v1/admin/internal-sales/{$number}/cancel", ['reason' => 'Test'])->assertForbidden();
    $this->actingAs($outsider, 'sanctum')->postJson('/api/v1/admin/cash-closings', ['counted_cash' => 0])->assertForbidden();
    $this->actingAs($outsider, 'sanctum')->getJson('/api/v1/admin/cash-closings/current')->assertForbidden();
});

it('shows what the till should hold: cash sales minus cash refunds, mobile money apart', function () {
    $user = cashier();
    ring($user);                                   // +100 000 cash
    [$cancelled] = ring($user, 30_000, 1);         // +30 000 cash, then cancelled
    ring($user, 20_000, 1, 'mobile_money');        // not in the drawer
    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/internal-sales/{$cancelled}/cancel", ['reason' => 'Erreur'])->assertOk();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/cash-closings/current')->assertOk()
        ->assertJsonPath('sales_count', 3)->assertJsonPath('cancellations_count', 1)
        ->assertJsonPath('cash_sales', 130_000)->assertJsonPath('cash_refunds', 30_000)
        ->assertJsonPath('expected_cash', 100_000)->assertJsonPath('mobile_money_net', 20_000);
});

it('closes the till with the variance, then starts a clean period', function () {
    $user = cashier();
    ring($user);

    $closing = $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/cash-closings', ['counted_cash' => 99_000, 'note' => 'Billet de 1 000 manquant'])
        ->assertCreated()->assertJsonPath('expected_cash', 100_000)->assertJsonPath('counted_cash', 99_000)->assertJsonPath('variance', -1_000)
        ->assertJsonPath('user_name', $user->name);

    expect($closing->json('number'))->toStartWith('CLO-');
    $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/cash-closings/current')->assertJsonPath('sales_count', 0)->assertJsonPath('expected_cash', 0);
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/cash-closings', ['counted_cash' => 0])->assertUnprocessable();
    expect(CashClosing::query()->count())->toBe(1);
});

it('counts a refund in the period it is paid out, never rewriting a closed one', function () {
    $user = cashier();
    [$number] = ring($user);
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/cash-closings', ['counted_cash' => 100_000])->assertCreated()->assertJsonPath('variance', 0);

    $this->actingAs($user, 'sanctum')->postJson("/api/v1/admin/internal-sales/{$number}/cancel", ['reason' => 'Retour lendemain'])->assertOk();

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/cash-closings/current')
        ->assertJsonPath('sales_count', 0)->assertJsonPath('cancellations_count', 1)->assertJsonPath('expected_cash', -100_000);
    $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/cash-closings', ['counted_cash' => -0])->assertCreated()->assertJsonPath('expected_cash', -100_000);
});

it('keeps each cashier\'s drawer separate', function () {
    $a = cashier();
    $b = cashier();
    ring($a);
    ring($b, 10_000, 1);

    $this->actingAs($a, 'sanctum')->getJson('/api/v1/admin/cash-closings/current')->assertJsonPath('expected_cash', 100_000);
    $this->actingAs($b, 'sanctum')->getJson('/api/v1/admin/cash-closings/current')->assertJsonPath('expected_cash', 10_000);
});

it('delivers the closing report as a PDF and lists the closings', function () {
    $user = cashier();
    [$number] = ring($user);
    $id = $this->actingAs($user, 'sanctum')->postJson('/api/v1/admin/cash-closings', ['counted_cash' => 100_000])->json('id');

    $pdf = $this->actingAs($user, 'sanctum')->get("/api/v1/admin/cash-closings/{$id}/pdf")->assertOk();
    expect($pdf->getContent())->toStartWith('%PDF-')->and($pdf->headers->get('content-disposition'))->toContain(sprintf('CLO-%06d.pdf', $id));

    $html = app(App\Modules\Sales\Application\RenderClosingReport::class)->html(CashClosing::query()->findOrFail($id), $user->name);
    expect($html)->toContain('CLÔTURE DE CAISSE')->toContain($number)->toContain('data:image/png;base64,');

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/admin/cash-closings')->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($user, 'sanctum')->get('/api/v1/admin/cash-closings/9999/pdf')->assertNotFound();
});
