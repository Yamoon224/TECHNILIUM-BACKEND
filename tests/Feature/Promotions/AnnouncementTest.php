<?php

declare(strict_types=1);

use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Promotions\Infrastructure\Models\Announcement;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
    Storage::fake('public');
});

function announcementStaff(Role $role = Role::Marketing): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/**
 * @param  array<string, mixed>  $data
 */
function postAnnouncement(array $data = [], ?string $url = null): Illuminate\Testing\TestResponse
{
    return test()->actingAs(announcementStaff(), 'sanctum')->post($url ?? '/api/v1/admin/announcements', array_merge([
        'title' => 'Soldes',
        'image' => UploadedFile::fake()->image('a.jpg', 800, 500),
        'width' => 600,
        'height' => 400,
        'delay_seconds' => 5,
    ], $data), ['Accept' => 'application/json']);
}

it('keeps the admin endpoints to promotion managers', function () {
    $this->getJson('/api/v1/admin/announcements')->assertUnauthorized();

    foreach ([Role::Support, Role::Client, Role::VendeurTiers] as $role) {
        $this->actingAs(announcementStaff($role), 'sanctum')->getJson('/api/v1/admin/announcements')->assertForbidden();
    }
});

it('creates an announcement with its image, size, delay and period', function () {
    $response = postAnnouncement(['link_url' => '/?category=smartphones', 'starts_at' => '2026-11-01 08:00:00', 'ends_at' => '2026-11-10 20:00:00'])
        ->assertCreated()
        ->assertJsonPath('width', 600)
        ->assertJsonPath('height', 400)
        ->assertJsonPath('delay_seconds', 5)
        ->assertJsonPath('link_url', '/?category=smartphones');

    Storage::disk('public')->assertExists(Announcement::findOrFail($response->json('id'))->path);
});

it('rejects a missing image, odd sizes, an unsafe link and an end before the start', function () {
    postAnnouncement(['image' => null])->assertUnprocessable()->assertJsonValidationErrors('image');
    postAnnouncement(['width' => 50, 'height' => 5000])->assertUnprocessable()->assertJsonValidationErrors(['width', 'height']);
    postAnnouncement(['delay_seconds' => -1])->assertUnprocessable()->assertJsonValidationErrors('delay_seconds');
    postAnnouncement(['link_url' => 'javascript:alert(1)'])->assertUnprocessable()->assertJsonValidationErrors('link_url');
    postAnnouncement(['starts_at' => '2026-11-10 00:00:00', 'ends_at' => '2026-11-01 00:00:00'])->assertUnprocessable()->assertJsonValidationErrors('ends_at');
});

it('shows publicly only active announcements within their period, newest first', function () {
    foreach ([
        ['title' => 'Open'],
        ['title' => 'Running', 'starts_at' => now()->subDay(), 'ends_at' => now()->addDay()],
        ['title' => 'Expired', 'starts_at' => now()->subDays(3), 'ends_at' => now()->subDay()],
        ['title' => 'Future', 'starts_at' => now()->addDay()],
        ['title' => 'Off', 'is_active' => false],
    ] as $row) {
        Announcement::create(['path' => 'announcements/x.jpg', ...$row]);
    }

    $this->getJson('/api/v1/announcements')
        ->assertOk()
        ->assertJsonCount(2)
        ->assertJsonPath('0.title', 'Running')
        ->assertJsonPath('1.title', 'Open');
});

it('replaces the image on update only when a new one is sent, and deletes files with the announcement', function () {
    $id = postAnnouncement()->json('id');
    $old = Announcement::findOrFail($id)->path;

    // Same image kept.
    postAnnouncement(['image' => null, 'title' => 'Renamed', 'is_active' => '0'], "/api/v1/admin/announcements/{$id}")
        ->assertOk()->assertJsonPath('title', 'Renamed')->assertJsonPath('is_active', false);
    expect(Announcement::findOrFail($id)->path)->toBe($old);

    // New image: the old file goes.
    postAnnouncement([], "/api/v1/admin/announcements/{$id}")->assertOk();
    $new = Announcement::findOrFail($id)->path;
    expect($new)->not->toBe($old);
    Storage::disk('public')->assertMissing($old);
    Storage::disk('public')->assertExists($new);

    $this->actingAs(announcementStaff(), 'sanctum')->deleteJson("/api/v1/admin/announcements/{$id}")->assertNoContent();
    expect(Announcement::count())->toBe(0);
    Storage::disk('public')->assertMissing($new);
});

it('archives an announcement: no longer shown, kept in the admin list, and restorable', function () {
    $id = postAnnouncement()->json('id');
    $this->getJson('/api/v1/announcements')->assertJsonCount(1);

    $staff = announcementStaff();
    $this->actingAs($staff, 'sanctum')->postJson("/api/v1/admin/announcements/{$id}/archive")
        ->assertOk()->assertJsonPath('archived_at', fn ($value) => $value !== null);

    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/announcements')->assertJsonCount(0);
    $this->actingAs($staff, 'sanctum')->getJson('/api/v1/admin/announcements')->assertJsonCount(1)->assertJsonPath('0.id', $id);

    $this->actingAs($staff, 'sanctum')->postJson("/api/v1/admin/announcements/{$id}/restore")
        ->assertOk()->assertJsonPath('archived_at', null);
    $this->app['auth']->forgetGuards();
    $this->getJson('/api/v1/announcements')->assertJsonCount(1);
});

it('stores an optional number of displays', function () {
    postAnnouncement(['max_displays' => 3])->assertCreated()->assertJsonPath('max_displays', 3);
    postAnnouncement(['max_displays' => ''])->assertCreated()->assertJsonPath('max_displays', null);
    postAnnouncement(['max_displays' => 0])->assertUnprocessable()->assertJsonValidationErrors('max_displays');
});
