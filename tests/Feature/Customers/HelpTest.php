<?php

declare(strict_types=1);

it('serves the help figures to anyone, with no contact when none is configured', function () {
    config(['returns.window_days' => 7, 'support.contact' => ['phone' => '', 'whatsapp' => '', 'email' => '', 'hours' => '']]);

    $this->getJson('/api/v1/help')->assertOk()
        ->assertJsonPath('return_window_days', 7)
        ->assertJsonPath('first_response_hours', (int) config('support.first_response_hours.normal'))
        ->assertJsonPath('contact', []);
});

it('follows the configured return window, so the page never states a stale figure', function () {
    config(['returns.window_days' => 14]);

    $this->getJson('/api/v1/help')->assertJsonPath('return_window_days', 14);
});

it('advertises only the channels that are set', function () {
    config(['support.contact' => ['phone' => '+225 07 00 00 00 00', 'whatsapp' => '', 'email' => 'aide@technilium.example', 'hours' => 'Lun-Sam 8h-18h']]);

    $contact = $this->getJson('/api/v1/help')->assertOk()->json('contact');

    expect($contact)->toBe(['phone' => '+225 07 00 00 00 00', 'email' => 'aide@technilium.example', 'hours' => 'Lun-Sam 8h-18h']);
});

it('exposes nothing personal or internal', function () {
    $keys = array_keys($this->getJson('/api/v1/help')->assertOk()->json());

    expect($keys)->toBe(['return_window_days', 'first_response_hours', 'contact']);
});
