<?php

declare(strict_types=1);

use App\Modules\Catalog\Domain\CatalogLocale;
use App\Modules\Catalog\Infrastructure\Models\Category;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function translatedProduct(array $overrides = []): Product
{
    $category = Category::factory()->create(['name' => 'Téléphones', 'name_en' => 'Phones']);

    return Product::factory()->create($overrides + [
        'category_id' => $category->id, 'status' => 'published',
        'name' => 'Galaxy A15 débloqué', 'name_en' => 'Galaxy A15 unlocked',
        'description' => 'Un téléphone fiable.', 'description_en' => 'A reliable phone.',
    ]);
}

it('picks the English text only when English is asked for and one exists', function () {
    expect(CatalogLocale::pick('en', 'Bonjour', 'Hello'))->toBe('Hello')
        ->and(CatalogLocale::pick('en', 'Bonjour', null))->toBe('Bonjour')
        ->and(CatalogLocale::pick('en', 'Bonjour', '   '))->toBe('Bonjour')
        ->and(CatalogLocale::pick('fr', 'Bonjour', 'Hello'))->toBe('Bonjour')
        ->and(CatalogLocale::resolve('en'))->toBe('en')
        ->and(CatalogLocale::resolve('de'))->toBe('fr')
        ->and(CatalogLocale::resolve(null))->toBe('fr');
});

it('serves French by default and English on ?lang=en, on the product page', function () {
    $product = translatedProduct();

    $this->getJson("/api/v1/catalog/products/{$product->slug}")->assertOk()
        ->assertJsonPath('name', 'Galaxy A15 débloqué')->assertJsonPath('description', 'Un téléphone fiable.')
        ->assertJsonPath('category.name', 'Téléphones');

    $this->getJson("/api/v1/catalog/products/{$product->slug}?lang=en")->assertOk()
        ->assertJsonPath('name', 'Galaxy A15 unlocked')->assertJsonPath('description', 'A reliable phone.')
        ->assertJsonPath('category.name', 'Phones');
});

it('falls back to French for what has no English text', function () {
    $product = translatedProduct(['name_en' => null, 'description_en' => '']);

    $this->getJson("/api/v1/catalog/products/{$product->slug}?lang=en")->assertOk()
        ->assertJsonPath('name', 'Galaxy A15 débloqué')->assertJsonPath('description', 'Un téléphone fiable.');
});

it('translates the product list and the category menu, and ignores an unknown language', function () {
    $product = translatedProduct();

    $list = $this->getJson('/api/v1/catalog/products?lang=en')->assertOk()->json('data');
    expect(collect($list)->firstWhere('id', $product->id)['name'])->toBe('Galaxy A15 unlocked');

    $categories = $this->getJson('/api/v1/catalog/categories?lang=en')->assertOk()->json();
    expect(collect($categories)->pluck('name')->all())->toContain('Phones');

    $this->getJson("/api/v1/catalog/products/{$product->slug}?lang=de")->assertJsonPath('name', 'Galaxy A15 débloqué');
});

it('finds a product by its English name too', function () {
    $product = translatedProduct();
    translatedProduct(['name' => 'Autre', 'name_en' => 'Other', 'brand' => 'Zzz']);

    $found = collect($this->getJson('/api/v1/catalog/products?search=unlocked&lang=en')->assertOk()->json('data'))->pluck('id')->all();

    expect($found)->toBe([$product->id]);
});

it('lets catalogue staff write the English texts, and returns both languages to them', function () {
    $staff = User::factory()->create();
    $staff->assignRole(Role::GestionnaireCatalogue->value);
    $category = Category::factory()->create();

    $created = $this->actingAs($staff, 'sanctum')->postJson('/api/v1/admin/products', [
        'category_id' => $category->id, 'brand' => 'Samsung', 'name' => 'Écran 27 pouces', 'name_en' => '27-inch monitor',
        'description' => 'Un écran.', 'description_en' => 'A monitor.', 'price_amount' => 100_000, 'stock_quantity' => 2,
    ])->assertCreated()->assertJsonPath('name_en', '27-inch monitor')->assertJsonPath('description_en', 'A monitor.');

    $this->actingAs($staff, 'sanctum')->putJson('/api/v1/admin/products/'.$created->json('id'), ['name_en' => '27" monitor'])
        ->assertOk()->assertJsonPath('name_en', '27" monitor')->assertJsonPath('name', 'Écran 27 pouces');

    $this->actingAs($staff, 'sanctum')->putJson('/api/v1/admin/categories/'.$category->id, ['name_en' => 'Screens'])
        ->assertOk()->assertJsonPath('name_en', 'Screens');
});
