<?php

use App\Modules\Catalog\CatalogModuleServiceProvider;
use App\Modules\Identity\IdentityModuleServiceProvider;
use App\Providers\AppServiceProvider;

return [
    AppServiceProvider::class,
    IdentityModuleServiceProvider::class,
    CatalogModuleServiceProvider::class,
];
