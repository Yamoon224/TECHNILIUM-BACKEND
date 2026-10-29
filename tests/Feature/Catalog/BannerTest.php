<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Banner;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
    Storage::fake('public');
});

function bannerStaff(): User
{
    $user = User::factory()->create();
    $user->assignRole(Role::GestionnaireCatalogue->value);

    return $user;
}

function addBanner(array $data = [], ?User $by = null): Illuminate\Testing\TestResponse
{
    return test()->actingAs($by ?? bannerStaff(), 'sanctum')->post('/api/v1/admin/banners', array_merge([
        'placement' => 'hero',
        'image' => UploadedFile::fake()->image('b.jpg', 1200, 400),
    ], $data), ['Accept' => 'application/json']);
}

it('refuses clients and anonymous visitors on the admin endpoints', function () {
    $this->getJson('/api/v1/admin/banners')->assertUnauthorized();

    $client = User::factory()->create();
    $client->assignRole(Role::Client->value);
    $this->actingAs($client, 'sanctum')->getJson('/api/v1/admin/banners')->assertForbidden();
});

it('adds slides in order and stores the files', function () {
    addBanner(['title' => 'Promo'])->assertCreated()->assertJsonPath('position', 0)->assertJsonPath('title', 'Promo');
    addBanner()->assertCreated()->assertJsonPath('position', 1);
    addBanner(['placement' => 'footer'])->assertCreated()->assertJsonPath('position', 0);

    expect(Banner::count())->toBe(3);
    foreach (Banner::all() as $banner) {
        Storage::disk('public')->assertExists($banner->path);
    }
});

it('rejects an unknown placement, a bad file, an unsafe link and a full banner', function () {
    addBanner(['placement' => 'side'])->assertUnprocessable()->assertJsonValidationErrors('placement');
    addBanner(['image' => UploadedFile::fake()->create('x.pdf', 10, 'application/pdf')])->assertUnprocessable()->assertJsonValidationErrors('image');
    addBanner(['image' => UploadedFile::fake()->image('small.jpg', 300, 300)])->assertUnprocessable()->assertJsonValidationErrors('image');
    addBanner(['link_url' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('link_url');

    foreach (range(1, Banner::MAX_PER_PLACEMENT) as $i) {
        addBanner()->assertCreated();
    }
    addBanner()->assertUnprocessable()->assertJsonValidationErrors('image');
    addBanner(['placement' => 'footer'])->assertCreated();
});

it('serves only active slides of the placement, publicly and in order', function () {
    addBanner(['title' => 'A'])->assertCreated();
    addBanner(['title' => 'B'])->assertCreated();
    addBanner(['title' => 'Hidden'])->assertCreated();
    addBanner(['placement' => 'footer', 'title' => 'F'])->assertCreated();

    $staff = bannerStaff();
    $hidden = Banner::where('title', 'Hidden')->first();
    $this->actingAs($staff, 'sanctum')->putJson("/api/v1/admin/banners/{$hidden->id}", ['is_active' => false])->assertOk();
    $b = Banner::where('title', 'B')->first();
    $this->actingAs($staff, 'sanctum')->putJson("/api/v1/admin/banners/{$b->id}", ['position' => 0, 'link_url' => '/?category=smartphones'])->assertOk();
    $a = Banner::where('title', 'A')->first();
    $this->actingAs($staff, 'sanctum')->putJson("/api/v1/admin/banners/{$a->id}", ['position' => 5])->assertOk();

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/catalog/banners?placement=hero')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.title', 'B')
        ->assertJsonPath('0.link_url', '/?category=smartphones')
        ->assertJsonPath('1.title', 'A');
    $this->getJson('/api/v1/catalog/banners?placement=footer')->assertJsonCount(1);
});

it('deletes a slide and its file', function () {
    $banner = Banner::find(addBanner()->json('id'));

    $this->actingAs(bannerStaff(), 'sanctum')->deleteJson("/api/v1/admin/banners/{$banner->id}")->assertNoContent();

    expect(Banner::count())->toBe(0);
    Storage::disk('public')->assertMissing($banner->path);
});
