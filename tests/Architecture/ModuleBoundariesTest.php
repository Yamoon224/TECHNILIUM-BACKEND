<?php

declare(strict_types=1);

/**
 * Enforces the modular-monolith rules from cahier des charges §9.1 / §10.3:
 * modules talk to each other only through Shared contracts/events, never by
 * reaching into another module's Infrastructure (Eloquent models) directly.
 */
arch('other modules do not reach into Identity\'s Infrastructure layer')
    ->expect([
        'App\Modules\Catalog',
        'App\Modules\Orders',
        'App\Modules\Vendors',
        'App\Modules\Inventory',
        'App\Modules\Payments',
        'App\Modules\Shipping',
    ])
    ->not->toUse('App\Modules\Identity\Infrastructure');

arch('Orders depends on Catalog\'s public Application contracts, never its Infrastructure')
    ->expect('App\Modules\Orders')
    ->not->toUse('App\Modules\Catalog\Infrastructure');

arch('Vendors depends on Catalog\'s public Application contracts, never its Infrastructure')
    ->expect('App\Modules\Vendors')
    ->not->toUse('App\Modules\Catalog\Infrastructure');

arch('Inventory depends on Catalog\'s public Application contracts, never its Infrastructure')
    ->expect('App\Modules\Inventory')
    ->not->toUse('App\Modules\Catalog\Infrastructure');

arch('Payments never reaches into Orders\' Infrastructure (order existence is checked by table name)')
    ->expect('App\Modules\Payments')
    ->not->toUse('App\Modules\Orders\Infrastructure');

arch('Shipping depends on Orders\' public Application contracts, never its Infrastructure')
    ->expect('App\Modules\Shipping')
    ->not->toUse('App\Modules\Orders\Infrastructure');

arch('Invoicing depends on Orders\' public Application contracts, never its Infrastructure')
    ->expect('App\Modules\Invoicing')
    ->not->toUse('App\Modules\Orders\Infrastructure');

arch('Accounting only learns about other modules through Shared events')
    ->expect('App\Modules\Accounting')
    ->not->toUse([
        'App\Modules\Orders',
        'App\Modules\Payments',
        'App\Modules\Invoicing',
    ]);

arch('Payments and Invoicing never reach into Accounting')
    ->expect(['App\Modules\Payments', 'App\Modules\Invoicing'])
    ->not->toUse('App\Modules\Accounting');

arch('value objects have no framework dependency')
    ->expect('App\Modules\Shared\ValueObjects')
    ->toOnlyUse([
        'App\Modules\Shared\Exceptions',
        'InvalidArgumentException',
        'DomainException',
    ]);

arch('domain exceptions are final')
    ->expect('App\Modules\Shared\Exceptions')
    ->toBeFinal();

arch('no debugging leftovers in application code')
    ->expect(['dd', 'dump', 'ray', 'var_dump'])
    ->not->toBeUsed();

arch('form requests live under Http\Requests')
    ->expect('App\Modules\Identity\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('API resources live under Http\Resources')
    ->expect('App\Modules\Identity\Http\Resources')
    ->toExtend('Illuminate\Http\Resources\Json\JsonResource');

arch('Orders form requests live under Http\Requests')
    ->expect('App\Modules\Orders\Http\Requests')
    ->toExtend('Illuminate\Foundation\Http\FormRequest');

arch('Orders API resources live under Http\Resources')
    ->expect('App\Modules\Orders\Http\Resources')
    ->toExtend('Illuminate\Http\Resources\Json\JsonResource');
