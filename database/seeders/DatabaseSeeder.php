<?php

namespace Database\Seeders;

use App\Modules\Catalog\Infrastructure\Seeders\CatalogDemoSeeder;
use App\Modules\Identity\Infrastructure\Seeders\IdentitySeeder;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->call([
            IdentitySeeder::class,
            CatalogDemoSeeder::class,
        ]);
    }
}
