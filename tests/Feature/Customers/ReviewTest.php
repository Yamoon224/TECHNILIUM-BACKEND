<?php

declare(strict_types=1);

use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Catalog\Infrastructure\Models\ProductVariant;
use App\Modules\Customers\Domain\ReviewStatus;
use App\Modules\Customers\Infrastructure\Models\Review;
use App\Modules\Identity\Domain\Role;
use App\Modules\Identity\Infrastructure\Models\User;
use App\Modules\Orders\Domain\OrderStatus;
use App\Modules\Orders\Infrastructure\Models\Order;
use App\Modules\Orders\Infrastructure\Models\OrderItem;
use Spatie\Permission\Models\Role as RoleModel;

beforeEach(function () {
    foreach (Role::cases() as $role) {
        RoleModel::findOrCreate($role->value, 'web');
    }
});

function buyer(string $name = 'Awa Diallo'): User
{
    $user = User::factory()->create(['name' => $name]);
    $user->assignRole(Role::Client->value);

    return $user;
}

function moderator(Role $role = Role::Marketing): User
{
    $user = User::factory()->create();
    $user->assignRole($role->value);

    return $user;
}

/** A published product with one offer. */
function reviewableProduct(): array
{
    $product = Product::factory()->create();
    $variant = ProductVariant::factory()->create(['product_id' => $product->id]);
    $offer = Offer::factory()->create(['product_variant_id' => $variant->id]);

    return [$product, $offer];
}

/** The customer receives the offer in a delivered order. */
function deliverTo(User $user, Offer $offer, OrderStatus $status = OrderStatus::Delivered): Order
{
    $order = Order::factory()->create(['customer_id' => $user->id, 'status' => $status]);
    OrderItem::factory()->create(['order_id' => $order->id, 'offer_id' => $offer->id]);

    return $order;
}

function postReview(User $user, int $productId, array $overrides = [])
{
    return test()->actingAs($user, 'sanctum')->postJson('/api/v1/reviews', $overrides + [
        'product_id' => $productId, 'rating' => 5, 'title' => 'Excellent', 'body' => 'Très satisfait de mon achat.',
    ]);
}

it('requires an account to review, and a valid rating', function () {
    [$product] = reviewableProduct();

    $this->postJson('/api/v1/reviews', ['product_id' => $product->id, 'rating' => 5])->assertUnauthorized();

    $user = buyer();
    foreach ([0, 6, 'x'] as $bad) {
        postReview($user, $product->id, ['rating' => $bad])->assertUnprocessable();
    }
});

it('publishes at once a clean review from a verified buyer', function () {
    [$product, $offer] = reviewableProduct();
    $user = buyer('Awa Diallo');
    $order = deliverTo($user, $offer);

    postReview($user, $product->id)
        ->assertCreated()
        ->assertJsonPath('status', 'published')
        ->assertJsonPath('author_name', 'Awa D.')
        ->assertJsonPath('verified_purchase', true);

    expect(Review::query()->sole()->order_number)->toBe($order->order_number);
});

it('refuses a review from someone who never received the product', function (string $case) {
    [$product, $offer] = reviewableProduct();
    $user = buyer();

    match ($case) {
        'never ordered' => null,
        'only shipped' => deliverTo($user, $offer, OrderStatus::Shipped),
        'only cancelled' => deliverTo($user, $offer, OrderStatus::Cancelled),
        'someone else received it' => deliverTo(buyer(), $offer),
        'guest order' => Order::factory()->create(['customer_id' => null, 'status' => OrderStatus::Delivered]),
        'another product' => deliverTo($user, reviewableProduct()[1]),
    };

    postReview($user, $product->id)->assertUnprocessable();
    expect(Review::query()->count())->toBe(0);
})->with(['never ordered', 'only shipped', 'only cancelled', 'someone else received it', 'guest order', 'another product']);

it('refuses a second review of the same product, even from another order', function () {
    [$product, $offer] = reviewableProduct();
    $user = buyer();
    deliverTo($user, $offer);
    deliverTo($user, $offer);

    postReview($user, $product->id)->assertCreated();
    postReview($user, $product->id)->assertUnprocessable();

    expect(Review::query()->count())->toBe(1);
});

it('refuses a review of an unpublished or unknown product', function () {
    $draft = Product::factory()->draft()->create();
    $user = buyer();

    postReview($user, $draft->id)->assertUnprocessable();
    postReview($user, 999999)->assertUnprocessable();
});

it('holds a flagged review for moderation until a person publishes it', function () {
    [$product, $offer] = reviewableProduct();
    $user = buyer();
    deliverTo($user, $offer);

    $created = postReview($user, $product->id, ['body' => 'Super, voir mes offres sur www.promo-tech.net'])
        ->assertCreated()->assertJsonPath('status', 'pending');

    // Not public yet.
    $this->getJson("/api/v1/reviews/products/{$product->id}")
        ->assertOk()->assertJsonPath('summary.count', 0)->assertJsonCount(0, 'reviews.data');

    $this->actingAs(moderator(), 'sanctum')->postJson('/api/v1/admin/reviews/'.$created->json('id').'/publish')
        ->assertOk()->assertJsonPath('status', 'published');

    $this->getJson("/api/v1/reviews/products/{$product->id}")->assertJsonPath('summary.count', 1);
});

it('honours the configured banned words', function () {
    config(['reviews.banned_words' => ['arnaque']]);
    [$product, $offer] = reviewableProduct();
    $user = buyer();
    deliverTo($user, $offer);

    postReview($user, $product->id, ['body' => 'Quelle ARNAQUE'])->assertCreated()->assertJsonPath('status', 'pending');
});

it('shows only published reviews publicly, with the average and the distribution', function () {
    $product = Product::factory()->create();
    Review::factory()->count(3)->create(['product_id' => $product->id, 'rating' => 5]);
    Review::factory()->create(['product_id' => $product->id, 'rating' => 2]);
    Review::factory()->status(ReviewStatus::Pending)->create(['product_id' => $product->id, 'rating' => 1]);
    Review::factory()->status(ReviewStatus::Rejected)->create(['product_id' => $product->id, 'rating' => 1]);
    Review::factory()->create(['rating' => 1]); // another product

    $response = $this->getJson("/api/v1/reviews/products/{$product->id}")->assertOk();

    $response->assertJsonPath('summary.count', 4)
        // (3 × 5 + 2) / 4 = 4.25, rounded half up to one decimal.
        ->assertJsonPath('summary.average', 4.3)
        ->assertJsonPath('summary.distribution.5', 3)
        ->assertJsonPath('summary.distribution.2', 1)
        ->assertJsonPath('summary.distribution.1', 0)
        ->assertJsonCount(4, 'reviews.data');
});

it('keeps private data out of public reviews', function () {
    $product = Product::factory()->create();
    Review::factory()->create(['product_id' => $product->id, 'customer_id' => 4242, 'order_number' => 'TCH-SECRET1']);

    $body = json_encode($this->getJson("/api/v1/reviews/products/{$product->id}")->json());

    expect($body)->not->toContain('TCH-SECRET1')->not->toContain('4242')->not->toContain('customer_id')->not->toContain('rejection');
});

it('tells a customer whether they may review, and why not', function () {
    [$product, $offer] = reviewableProduct();
    $user = buyer();
    $url = "/api/v1/reviews/products/{$product->id}/eligibility";

    $this->getJson($url)->assertUnauthorized();

    $this->actingAs($user, 'sanctum')->getJson($url)
        ->assertOk()->assertJsonPath('can_review', false)->assertJsonPath('reason', 'not_purchased');

    deliverTo($user, $offer);
    $this->actingAs($user, 'sanctum')->getJson($url)
        ->assertOk()->assertJsonPath('can_review', true)->assertJsonPath('reason', null);

    postReview($user, $product->id)->assertCreated();
    $this->actingAs($user, 'sanctum')->getJson($url)
        ->assertOk()->assertJsonPath('can_review', false)->assertJsonPath('reason', 'already_reviewed');

    // The order number that proves the purchase is never sent to the client.
    expect(json_encode($this->actingAs($user, 'sanctum')->getJson($url)->json()))->not->toContain('order_number');
});

it('lists a customer\'s own reviews with their state, and only theirs', function () {
    $user = buyer();
    Review::factory()->status(ReviewStatus::Rejected)->create(['customer_id' => $user->id, 'rejection_reason' => 'Hors sujet']);
    Review::factory()->create(['customer_id' => buyer()->id]);

    $this->actingAs($user, 'sanctum')->getJson('/api/v1/reviews/mine')
        ->assertOk()
        ->assertJsonCount(1, 'data')
        ->assertJsonPath('data.0.status', 'rejected')
        ->assertJsonPath('data.0.rejection_reason', 'Hors sujet');
});

it('restricts moderation to support, marketing and administrators', function () {
    $this->getJson('/api/v1/admin/reviews')->assertUnauthorized();

    foreach ([Role::Client, Role::Comptable, Role::VendeurTiers, Role::Logistique] as $role) {
        $this->actingAs(moderator($role), 'sanctum')->getJson('/api/v1/admin/reviews')->assertForbidden();
    }

    foreach ([Role::Support, Role::Marketing, Role::Administrateur] as $role) {
        $this->actingAs(moderator($role), 'sanctum')->getJson('/api/v1/admin/reviews')->assertOk();
    }
});

it('puts the reviews waiting for a decision first, and filters by status and product', function () {
    $product = Product::factory()->create();
    Review::factory()->create(['product_id' => $product->id]);
    $pending = Review::factory()->status(ReviewStatus::Pending)->create(['product_id' => $product->id]);
    Review::factory()->status(ReviewStatus::Rejected)->create();
    $staff = moderator();

    $this->actingAs($staff, 'sanctum')->getJson('/api/v1/admin/reviews')
        ->assertOk()->assertJsonPath('data.0.id', $pending->id)->assertJsonPath('data.0.status', 'pending');

    $this->actingAs($staff, 'sanctum')->getJson('/api/v1/admin/reviews?status=rejected')->assertJsonCount(1, 'data');
    $this->actingAs($staff, 'sanctum')->getJson("/api/v1/admin/reviews?product_id={$product->id}")->assertJsonCount(2, 'data');
});

it('rejects a review with a reason, takes a published one down, and can restore it', function () {
    $review = Review::factory()->create();
    $staff = moderator();

    $this->actingAs($staff, 'sanctum')->postJson("/api/v1/admin/reviews/{$review->id}/reject", ['reason' => 'Hors sujet'])
        ->assertOk()
        ->assertJsonPath('status', 'rejected')
        ->assertJsonPath('rejection_reason', 'Hors sujet')
        ->assertJsonPath('moderated_by', $staff->id);
    expect($review->fresh()->published_at)->toBeNull();

    // Not repeated.
    $this->actingAs($staff, 'sanctum')->postJson("/api/v1/admin/reviews/{$review->id}/reject")->assertUnprocessable();

    $this->actingAs($staff, 'sanctum')->postJson("/api/v1/admin/reviews/{$review->id}/publish")
        ->assertOk()->assertJsonPath('status', 'published')->assertJsonPath('rejection_reason', null);
    $this->actingAs($staff, 'sanctum')->postJson("/api/v1/admin/reviews/{$review->id}/publish")->assertUnprocessable();
});

it('removes a rejected review from the public average', function () {
    $product = Product::factory()->create();
    $review = Review::factory()->create(['product_id' => $product->id, 'rating' => 1]);
    Review::factory()->create(['product_id' => $product->id, 'rating' => 5]);

    $this->getJson("/api/v1/reviews/products/{$product->id}")->assertJsonPath('summary.average', 3);

    $this->actingAs(moderator(), 'sanctum')->postJson("/api/v1/admin/reviews/{$review->id}/reject");

    $this->getJson("/api/v1/reviews/products/{$product->id}")->assertJsonPath('summary.average', 5)->assertJsonPath('summary.count', 1);
});
