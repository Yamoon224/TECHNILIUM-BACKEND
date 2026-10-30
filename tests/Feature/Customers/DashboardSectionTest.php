<?php

declare(strict_types=1);

use App\Modules\Customers\Application\Dashboard\ReviewsSection;
use App\Modules\Customers\Application\Dashboard\SupportSection;
use App\Modules\Customers\Domain\ReviewStatus;
use App\Modules\Customers\Domain\TicketPriority;
use App\Modules\Customers\Domain\TicketStatus;
use App\Modules\Customers\Infrastructure\Models\Review;
use App\Modules\Customers\Infrastructure\Models\Ticket;
use App\Modules\Identity\Domain\AccessCatalogue;
use Carbon\CarbonImmutable;

function customersKpi(array $section, string $key): array
{
    return collect($section['kpis'])->firstWhere('key', $key);
}

it('declares known abilities and keys', function () {
    $support = app(SupportSection::class);
    $reviews = app(ReviewsSection::class);

    expect($support->key())->toBe('support')
        ->and($reviews->key())->toBe('reviews')
        ->and(AccessCatalogue::keys())->toContain($support->ability(), $reviews->ability());
});

it('summarises the support backlog', function () {
    $now = CarbonImmutable::now();

    // High priority target is 2 h: unanswered for 5 h means late.
    Ticket::factory()->priority(TicketPriority::High)->create(['subject' => 'Colis perdu', 'created_at' => $now->subHours(5)]);
    // Normal priority target is 8 h: still within the target.
    Ticket::factory()->create(['subject' => 'Question', 'created_at' => $now->subHour()]);
    // Answered by staff: parked on the customer.
    Ticket::factory()->status(TicketStatus::PendingCustomer)->create(['first_responded_at' => $now->subHour(), 'created_at' => $now->subDays(3)]);
    Ticket::factory()->status(TicketStatus::Resolved)->create(['resolved_at' => $now->subDay()]);

    $section = app(SupportSection::class)->build($now->subDays(6)->startOfDay(), $now->endOfDay());

    expect($section['link'])->toBe('/support')
        ->and(customersKpi($section, 'open')['value'])->toBe(3)
        ->and(customersKpi($section, 'unanswered')['value'])->toBe(2)
        ->and(customersKpi($section, 'unanswered')['tone'])->toBe('warn')
        ->and(customersKpi($section, 'sla_overdue')['value'])->toBe(1)
        ->and(customersKpi($section, 'sla_overdue')['tone'])->toBe('bad')
        ->and(customersKpi($section, 'resolved')['value'])->toBe(1);

    $byStatus = collect($section['charts'][0]['points'])->pluck('value', 'label');
    expect($section['charts'][0]['type'])->toBe('donut')
        ->and($byStatus['Ouvert'])->toBe(2)
        ->and($byStatus['Résolu'])->toBe(1);

    $rows = $section['tables'][0]['rows'];
    expect($rows)->toHaveCount(3)
        // Oldest first: the 3-day-old ticket waiting for the customer.
        ->and($rows[0][2])->toBe('3 j')
        ->and($rows[0][3])->toBe('En attente du client')
        ->and($rows[1][0])->toContain('Colis perdu');
});

it('copes with an empty support database', function () {
    $now = CarbonImmutable::now();
    $section = app(SupportSection::class)->build($now->subDays(6)->startOfDay(), $now->endOfDay());

    expect(customersKpi($section, 'open')['value'])->toBe(0)
        ->and(customersKpi($section, 'unanswered')['tone'])->toBeNull()
        ->and(customersKpi($section, 'sla_overdue')['value'])->toBe(0)
        ->and($section['charts'][0]['points'])->toBe([])
        ->and($section['tables'][0]['rows'])->toBe([]);
});

it('summarises the review moderation queue', function () {
    $now = CarbonImmutable::now();

    Review::factory()->create(['rating' => 5]);
    Review::factory()->create(['rating' => 4]);
    Review::factory()->create(['rating' => 4]);
    Review::factory()->status(ReviewStatus::Pending)->create(['rating' => 1, 'title' => 'Décevant', 'body' => null]);
    Review::factory()->status(ReviewStatus::Rejected)->create();

    $section = app(ReviewsSection::class)->build($now->subDays(6)->startOfDay(), $now->endOfDay());

    expect($section['link'])->toBe('/avis')
        ->and(customersKpi($section, 'to_moderate')['value'])->toBe(1)
        ->and(customersKpi($section, 'to_moderate')['tone'])->toBe('warn')
        ->and(customersKpi($section, 'published')['value'])->toBe(3)
        ->and(customersKpi($section, 'average_rating')['value'])->toBe(4.3)
        ->and(customersKpi($section, 'rejected')['value'])->toBe(1);

    $points = $section['charts'][0]['points'];
    expect($points)->toHaveCount(5)
        ->and($points[3]['value'])->toBe(2)
        ->and($points[4]['value'])->toBe(1)
        ->and($points[0]['value'])->toBe(0);

    expect($section['tables'][0]['rows'])->toHaveCount(1)
        ->and($section['tables'][0]['rows'][0][1])->toBe('1/5')
        ->and($section['tables'][0]['rows'][0][2])->toBe('Décevant');
});

it('copes with an empty reviews database', function () {
    $now = CarbonImmutable::now();
    $section = app(ReviewsSection::class)->build($now->subDays(6)->startOfDay(), $now->endOfDay());

    expect(customersKpi($section, 'to_moderate')['value'])->toBe(0)
        ->and(customersKpi($section, 'average_rating')['value'])->toBe(0.0)
        ->and($section['charts'][0]['points'])->toHaveCount(5)
        ->and($section['tables'][0]['rows'])->toBe([]);
});
