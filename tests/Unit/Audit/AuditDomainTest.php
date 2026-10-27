<?php

declare(strict_types=1);

use App\Modules\Audit\Domain\AuditChain;
use App\Modules\Audit\Domain\PayloadSanitizer;

it('masks secrets by key, whatever the case and nesting', function () {
    $clean = PayloadSanitizer::sanitize([
        'password' => 'hunter2',
        'Password_Confirmation' => 'hunter2',
        'api_token' => 'abc',
        'confirmation_code' => '482913',
        'delivery_code' => '111111',
        'payload' => ['client_secret' => 'x', 'nested' => ['card_number' => '4111']],
        'amount' => 5_000,
    ]);

    expect($clean['password'])->toBe(PayloadSanitizer::REDACTED)
        ->and($clean['Password_Confirmation'])->toBe(PayloadSanitizer::REDACTED)
        ->and($clean['api_token'])->toBe(PayloadSanitizer::REDACTED)
        ->and($clean['confirmation_code'])->toBe(PayloadSanitizer::REDACTED)
        ->and($clean['delivery_code'])->toBe(PayloadSanitizer::REDACTED)
        ->and($clean['payload']['client_secret'])->toBe(PayloadSanitizer::REDACTED)
        ->and($clean['payload']['nested']['card_number'])->toBe(PayloadSanitizer::REDACTED)
        ->and($clean['amount'])->toBe(5_000);
});

it('keeps ordinary fields, including a coupon code that merely contains "code"', function () {
    $clean = PayloadSanitizer::sanitize(['coupon_code' => 'BIENVENUE10', 'reason' => 'Erreur', 'items' => [['offer_id' => 1]]]);

    expect($clean)->toBe(['coupon_code' => 'BIENVENUE10', 'reason' => 'Erreur', 'items' => [['offer_id' => 1]]]);
});

it('truncates long strings, deep nesting and huge payloads', function () {
    $clean = PayloadSanitizer::sanitize([
        'note' => str_repeat('a', 500),
        'deep' => ['a' => ['b' => ['c' => ['d' => ['e' => 'too deep']]]]],
    ]);

    expect(mb_strlen($clean['note']))->toBe(201)
        ->and($clean['deep']['a']['b']['c']['d'])->toBe('[tronqué]');

    $many = PayloadSanitizer::sanitize(array_fill(0, 80, 'x'));
    expect(count($many))->toBe(51)->and($many['…'])->toBe('[tronqué]');
});

it('never stores an object or a resource', function () {
    expect(PayloadSanitizer::sanitize(['o' => new stdClass])['o'])->toBe('[non affichable]');
});

it('signs deterministically and independently of key order', function () {
    $a = AuditChain::sign(['x' => 1, 'y' => ['b' => 2, 'a' => 1]], AuditChain::GENESIS, 'secret');
    $b = AuditChain::sign(['y' => ['a' => 1, 'b' => 2], 'x' => 1], AuditChain::GENESIS, 'secret');

    expect($a)->toBe($b)->and($a)->toHaveLength(64);
});

it('changes the signature when the content, the previous hash or the key changes', function () {
    $base = AuditChain::sign(['x' => 1], AuditChain::GENESIS, 'secret');

    expect(AuditChain::sign(['x' => 2], AuditChain::GENESIS, 'secret'))->not->toBe($base)
        ->and(AuditChain::sign(['x' => 1], 'another-previous', 'secret'))->not->toBe($base)
        ->and(AuditChain::sign(['x' => 1], AuditChain::GENESIS, 'other-key'))->not->toBe($base);
});

it('validates only the exact content it signed', function () {
    $hash = AuditChain::sign(['x' => 1], AuditChain::GENESIS, 'secret');

    expect(AuditChain::isValid(['x' => 1], AuditChain::GENESIS, $hash, 'secret'))->toBeTrue()
        ->and(AuditChain::isValid(['x' => 9], AuditChain::GENESIS, $hash, 'secret'))->toBeFalse();
});
