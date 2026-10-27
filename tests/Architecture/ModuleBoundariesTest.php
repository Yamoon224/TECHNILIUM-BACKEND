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
        'App\Modules\Payouts',
    ]);

arch('Payments and Invoicing never reach into Accounting')
    ->expect(['App\Modules\Payments', 'App\Modules\Invoicing', 'App\Modules\Payouts'])
    ->not->toUse('App\Modules\Accounting');

arch('Payouts only uses Orders public Application contracts, never Orders or Vendors Infrastructure')
    ->expect('App\Modules\Payouts')
    ->not->toUse(['App\Modules\Orders\Infrastructure', 'App\Modules\Vendors\Infrastructure']);

arch('Returns never reaches into Orders Infrastructure')
    ->expect('App\Modules\Returns')
    ->not->toUse('App\Modules\Orders\Infrastructure');

arch('Payments, Invoicing and Inventory only hear about Returns through Shared events')
    ->expect(['App\Modules\Payments', 'App\Modules\Invoicing', 'App\Modules\Inventory'])
    ->not->toUse('App\Modules\Returns');

arch('Reporting reads other modules only through their public contracts, never their Infrastructure')
    ->expect('App\Modules\Reporting')
    ->not->toUse([
        'App\Modules\Orders\Infrastructure',
        'App\Modules\Payments\Infrastructure',
        'App\Modules\Payouts\Infrastructure',
        'App\Modules\Returns\Infrastructure',
        'App\Modules\Catalog\Infrastructure',
        'App\Modules\Vendors\Infrastructure',
    ]);

arch('Orders depends on Promotions only through its Application contract')
    ->expect('App\Modules\Orders')
    ->not->toUse('App\Modules\Promotions\Infrastructure');

arch('Promotions knows nothing of Orders')
    ->expect('App\Modules\Promotions')
    ->not->toUse('App\Modules\Orders');

arch('Notifications learns about other modules only through Shared events and Orders\' public contract')
    ->expect('App\Modules\Notifications')
    ->not->toUse([
        'App\Modules\Orders\Infrastructure',
        'App\Modules\Returns',
        'App\Modules\Payments',
        'App\Modules\Vendors',
    ]);

arch('Nothing else depends on Notifications')
    ->expect(['App\Modules\Orders', 'App\Modules\Returns', 'App\Modules\Payments', 'App\Modules\Invoicing'])
    ->not->toUse('App\Modules\Notifications');

arch('Audit does not depend on the business modules it watches')
    ->expect('App\Modules\Audit')
    ->not->toUse([
        'App\Modules\Orders',
        'App\Modules\Payments',
        'App\Modules\Invoicing',
        'App\Modules\Identity\Infrastructure',
    ]);

arch('Customers reads Orders only through its public contract, and touches no other module')
    ->expect('App\Modules\Customers')
    ->not->toUse([
        'App\Modules\Orders\Infrastructure',
        'App\Modules\Identity\Infrastructure',
        'App\Modules\Payments',
        'App\Modules\Returns',
    ]);

arch('Payouts never reaches into Returns Infrastructure')
    ->expect('App\Modules\Payouts')
    ->not->toUse('App\Modules\Returns\Infrastructure');

arch('The vendor space reads Orders and Payouts only through their public contracts')
    ->expect('App\Modules\Vendors')
    ->not->toUse(['App\Modules\Orders\Infrastructure', 'App\Modules\Payouts\Infrastructure']);

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
