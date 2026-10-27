<?php

declare(strict_types=1);

use App\Modules\Notifications\Application\Channels\LogChannel;
use App\Modules\Notifications\Domain\MessageTemplates;

it('formats F CFA amounts with a space as thousands separator, other currencies with their code', function () {
    expect(MessageTemplates::formatAmount(1_250_000, 'XOF'))->toBe('1 250 000 F CFA')
        ->and(MessageTemplates::formatAmount(500, 'XAF'))->toBe('500 F CFA')
        ->and(MessageTemplates::formatAmount(4_990, 'EUR'))->toBe('4 990 EUR');
});

it('tells the customer their order is placed, with the amount and the delivery code', function () {
    $message = MessageTemplates::orderPlaced('Awa Diallo', 'TCH-1', 90_000, 'XOF', '482913');

    expect($message)->toContain('Awa')->not->toContain('Diallo')
        ->and($message)->toContain('TCH-1')
        ->and($message)->toContain('90 000 F CFA')
        ->and($message)->toContain('482913')
        ->and($message)->toContain('paiement à la livraison');
});

it('omits the code sentence when the order has none', function () {
    expect(MessageTemplates::orderPlaced('Awa', 'TCH-1', 1_000, 'XOF', null))->not->toContain('Code de livraison');
});

it('words each customer-facing status, and stays silent for the others', function (string $status, string $needle) {
    expect(MessageTemplates::orderStatus('Awa Diallo', 'TCH-1', $status))->toContain('TCH-1')->toContain($needle);
})->with([
    ['confirmed', 'confirmée'],
    ['shipped', 'expédiée'],
    ['delivered', 'livrée'],
    ['cancelled', 'annulée'],
]);

it('sends nothing for a status the customer is not told about', function () {
    expect(MessageTemplates::orderStatus('Awa', 'TCH-1', 'pending'))->toBeNull()
        ->and(MessageTemplates::orderStatus('Awa', 'TCH-1', 'whatever'))->toBeNull();
});

it('announces a refunded return with its amount', function () {
    expect(MessageTemplates::returnRefunded('TCH-1', 30_000, 'XOF'))->toContain('TCH-1')->toContain('30 000 F CFA');
});

it('falls back to "client" when the name is blank', function () {
    expect(MessageTemplates::orderStatus('  ', 'TCH-1', 'shipped'))->toContain('client');
});

it('masks a phone number for the log', function () {
    expect(LogChannel::mask('0700000001'))->toBe('07******01')
        ->and(LogChannel::mask('1234'))->toBe('****');
});
