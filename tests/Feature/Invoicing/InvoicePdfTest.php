<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Invoicing\Application\RenderInvoiceDocument;
use App\Modules\Invoicing\Domain\InvoiceType;
use App\Modules\Invoicing\Infrastructure\Models\Invoice;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function docUser(Role $role = Role::Comptable): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** An issued invoice (118 000 TTC = 100 000 HT + 18 000 VAT) with one line. */
function issuedInvoice(array $overrides = []): Invoice
{
    $invoice = Invoice::factory()->create($overrides + [
        'number' => 'FAC-2026-000042',
        'order_number' => 'TCH-20261027-ABC123',
        'customer_name' => 'Awa Diallo',
        'issued_at' => '2026-10-27 10:00:00',
    ]);
    $invoice->lines()->create([
        'description' => 'Samsung Galaxy A15', 'quantity' => 2, 'unit_price_amount' => 59_000, 'line_total_amount' => 118_000,
    ]);

    return $invoice;
}

function html(Invoice $invoice): string
{
    // Without the inline logo: its base64 would otherwise look like text on the page.
    return preg_replace('~data:image/png;base64,[A-Za-z0-9+/=]+~', '', app(RenderInvoiceDocument::class)->html($invoice));
}

it('prints the Technilium logo on the document', function () {
    expect(app(RenderInvoiceDocument::class)->html(issuedInvoice()))->toContain('<img src="data:image/png;base64,');
});

it('restricts the document to finance roles', function () {
    $invoice = issuedInvoice();

    $this->getJson("/api/v1/admin/invoices/{$invoice->id}/pdf")->assertUnauthorized();

    foreach ([Role::Support, Role::OperateurCommandes, Role::VendeurTiers, Role::Client] as $role) {
        $this->actingAs(docUser($role), 'sanctum')->get("/api/v1/admin/invoices/{$invoice->id}/pdf")->assertForbidden();
    }
});

it('downloads a real PDF named after the document number', function () {
    $invoice = issuedInvoice();

    $response = $this->actingAs(docUser(), 'sanctum')->get("/api/v1/admin/invoices/{$invoice->id}/pdf")->assertOk();

    expect($response->headers->get('content-type'))->toBe('application/pdf')
        ->and($response->headers->get('content-disposition'))->toContain('FAC-2026-000042.pdf')
        ->and($response->getContent())->toStartWith('%PDF-')
        ->and(strlen($response->getContent()))->toBeGreaterThan(1_000);
});

it('answers 404 for an unknown document', function () {
    $this->actingAs(docUser(), 'sanctum')->get('/api/v1/admin/invoices/999999/pdf')->assertNotFound();
});

it('prints the number, customer, order, lines, VAT and the total in words', function () {
    $page = html(issuedInvoice());

    expect($page)->toContain('FACTURE')
        ->and($page)->toContain('FAC-2026-000042')
        ->and($page)->toContain('27/10/2026')
        ->and($page)->toContain('Awa Diallo')
        ->and($page)->toContain('TCH-20261027-ABC123')
        ->and($page)->toContain('Samsung Galaxy A15')
        ->and($page)->toContain("100\u{00A0}000\u{00A0}F\u{00A0}CFA")     // total HT
        ->and($page)->toContain("18\u{00A0}000\u{00A0}F\u{00A0}CFA")        // VAT
        ->and($page)->toContain("118\u{00A0}000\u{00A0}F\u{00A0}CFA")       // total TTC
        ->and($page)->toContain('TVA (18 %)')
        ->and($page)->toContain('Cent dix-huit mille francs CFA');
});

it('shows the coupon discount and its code when there was one', function () {
    $page = html(issuedInvoice(['discount_amount' => 11_800, 'coupon_code' => 'BIENVENUE10']));

    expect($page)->toContain('Remise (BIENVENUE10)')->toContain("11\u{00A0}800\u{00A0}F\u{00A0}CFA");
    expect(html(issuedInvoice(['number' => 'FAC-2026-000043'])))->not->toContain('Remise');
});

it('names the invoice a credit note cancels, and its reason', function () {
    $invoice = issuedInvoice();
    $credit = Invoice::factory()->create([
        'number' => 'AV-2026-000001', 'type' => InvoiceType::CreditNote, 'credited_invoice_id' => $invoice->id,
        'order_number' => $invoice->order_number, 'customer_name' => 'Awa Diallo', 'reason' => 'Produit retourné',
        'total_amount' => 59_000, 'net_amount' => 50_000, 'vat_amount' => 9_000, 'issued_at' => '2026-10-28 09:00:00',
    ]);
    $credit->lines()->create(['description' => 'Avoir partiel - Produit retourné', 'quantity' => 1, 'unit_price_amount' => 59_000, 'line_total_amount' => 59_000]);

    // Blade escapes the apostrophe as &#039; (valid HTML, shown normally): compare the decoded text.
    $page = html_entity_decode(html($credit), ENT_QUOTES);

    expect($page)->toContain('AVOIR')
        ->and($page)->toContain('Annule la facture : FAC-2026-000042')
        ->and($page)->toContain('Produit retourné')
        ->and($page)->toContain('Total de l\'avoir TTC')
        ->and($page)->toContain('Cinquante-neuf mille francs CFA');

    $this->actingAs(docUser(), 'sanctum')->get("/api/v1/admin/invoices/{$credit->id}/pdf")->assertOk()
        ->assertHeader('content-type', 'application/pdf');
});

it('escapes untrusted text: a hostile customer name or reason cannot inject markup', function () {
    $page = html(issuedInvoice(['customer_name' => '<script>alert(1)</script> <img src="http://evil.example/x.png">']));

    expect($page)->not->toContain('<script>')
        ->and($page)->not->toContain('<img src="http')
        ->and($page)->toContain('&lt;script&gt;');
});

it('still produces a PDF for a name with accents, quotes and symbols', function () {
    $invoice = issuedInvoice(['customer_name' => "Élodie N'Guessan & Fils « SARL » - 100 %"]);

    $pdf = app(RenderInvoiceDocument::class)->pdf($invoice);

    expect($pdf)->toStartWith('%PDF-');
});

it('prints the issuer from configuration, and the legal line only when it is set', function () {
    config(['invoicing.company' => [
        'name' => 'Technilium SARL', 'address' => 'Abidjan, Cocody', 'phone' => '+225 07 00 00 00 00',
        'email' => 'facturation@technilium.example', 'legal' => 'RCCM CI-ABJ-2026-B-00000 - NCC 0000000A',
    ]]);

    $page = html(issuedInvoice());
    expect($page)->toContain('Technilium SARL')->toContain('Abidjan, Cocody')->toContain('RCCM CI-ABJ-2026-B-00000');

    config(['invoicing.company.legal' => '', 'invoicing.company.address' => '']);
    $bare = html(issuedInvoice(['number' => 'FAC-2026-000050']));
    expect($bare)->not->toContain('RCCM')->not->toContain('Abidjan, Cocody');
});

it('does not invent legal identifiers by default', function () {
    config(['invoicing.company.legal' => '']);

    expect(html(issuedInvoice()))->not->toContain('RCCM')->not->toContain('NCC');
});

it('renders the same document every time, since an issued invoice cannot change', function () {
    $invoice = issuedInvoice();

    expect(html($invoice))->toBe(html($invoice->fresh()));
});

it('renders an English version with English labels and the amount in English words', function () {
    $invoice = issuedInvoice();

    $page = app(RenderInvoiceDocument::class)->html($invoice, 'en');

    expect($page)->toContain('INVOICE')->toContain('Customer')->toContain('Total incl. VAT')->toContain('VAT (18 %)')
        ->toContain('One hundred eighteen thousand CFA francs')
        ->toContain('Awa Diallo')->toContain('FAC-2026-000042')->toContain("118\u{00A0}000\u{00A0}F\u{00A0}CFA")
        ->not->toContain('FACTURE')->not->toContain('francs CFA');
});

it('renders an English credit note, naming the invoice it cancels', function () {
    $invoice = issuedInvoice();
    $credit = Invoice::factory()->create([
        'number' => 'AV-2026-000001', 'type' => InvoiceType::CreditNote, 'credited_invoice_id' => $invoice->id,
        'order_number' => $invoice->order_number, 'customer_name' => 'Awa Diallo', 'reason' => 'Returned product',
        'total_amount' => 59_000, 'net_amount' => 50_000, 'vat_amount' => 9_000, 'issued_at' => '2026-10-28 09:00:00',
    ]);

    $page = html_entity_decode(app(RenderInvoiceDocument::class)->html($credit, 'en'), ENT_QUOTES);

    expect($page)->toContain('CREDIT NOTE')->toContain('Cancels invoice: FAC-2026-000042')->toContain('Credit note total incl. VAT')
        ->toContain('Fifty-nine thousand CFA francs');
});

it('falls back to French for an unknown language and stays French by default', function () {
    $invoice = issuedInvoice();

    expect(app(RenderInvoiceDocument::class)->html($invoice, 'de'))->toContain('FACTURE')
        ->and(html($invoice))->toContain('FACTURE');
});

it('lets staff ask for the English PDF, and gives French by default', function () {
    $invoice = issuedInvoice();
    $staff = docUser();

    $en = $this->actingAs($staff, 'sanctum')->get("/api/v1/admin/invoices/{$invoice->id}/pdf?lang=en")->assertOk();
    $fr = $this->actingAs($staff, 'sanctum')->get("/api/v1/admin/invoices/{$invoice->id}/pdf")->assertOk();

    expect($en->getContent())->toStartWith('%PDF-')->and($fr->getContent())->toStartWith('%PDF-')
        ->and($en->getContent())->not->toBe($fr->getContent());
});
