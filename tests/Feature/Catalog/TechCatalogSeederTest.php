<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Seeders\TechCatalogSeeder;
use Illuminate\Support\Facades\DB;

it('seeds the requested number of tech products with a variant and an offer each', function () {
    $created = (new TechCatalogSeeder)->generate(120);

    expect($created)->toBeGreaterThan(100)->toBeLessThanOrEqual(130)
        ->and(DB::table('products')->count())->toBe($created)
        ->and(DB::table('product_variants')->count())->toBe($created)
        ->and(DB::table('offers')->count())->toBe($created)
        ->and(DB::table('categories')->count())->toBeGreaterThanOrEqual(15);
});

it('gives every product unique slugs and SKUs, English texts and a positive XOF price', function () {
    (new TechCatalogSeeder)->generate(150);

    expect(DB::table('products')->distinct()->count('slug'))->toBe(DB::table('products')->count())
        ->and(DB::table('product_variants')->distinct()->count('sku'))->toBe(DB::table('product_variants')->count())
        ->and(DB::table('products')->whereNull('name_en')->orWhereNull('description_en')->count())->toBe(0)
        ->and(DB::table('offers')->where('price_amount', '<=', 0)->count())->toBe(0)
        ->and(DB::table('offers')->where('currency', '!=', 'XOF')->count())->toBe(0);
});

it('covers phones and sound equipment among the categories', function () {
    (new TechCatalogSeeder)->generate(300);

    $slugs = DB::table('categories')->pluck('slug')->all();
    expect($slugs)->toContain('telephones')->toContain('sono-dj')->toContain('barres-de-son');
    expect(DB::table('products')->where('category_id', DB::table('categories')->where('slug', 'telephones')->value('id'))->count())->toBeGreaterThan(0);
});

it('publishes most of the catalogue so the shop can browse it', function () {
    (new TechCatalogSeeder)->generate(200);

    $this->getJson('/api/v1/catalog/products')->assertOk();
    expect(DB::table('offers')->where('status', 'published')->count())->toBeGreaterThan(150);
});

it('is repeatable: running it again adds nothing', function () {
    $seeder = new TechCatalogSeeder;
    $first = $seeder->generate(80);
    $second = $seeder->generate(80);

    expect($first)->toBeGreaterThan(0)->and($second)->toBe(0)
        ->and(DB::table('products')->count())->toBe($first);
});
