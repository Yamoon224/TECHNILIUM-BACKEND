<?php

declare(strict_types=1);

namespace App\Modules\Customers\Application\Dashboard;

use App\Modules\Customers\Domain\ReviewStatus;
use App\Modules\Customers\Infrastructure\Models\Review;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;
use Illuminate\Support\Str;

/**
 * Dashboard block of the reviews feature: the moderation queue, what was
 * published or rejected in the period and the spread of published ratings.
 */
final class ReviewsSection implements DashboardSection
{
    public function key(): string
    {
        return 'reviews';
    }

    public function ability(): string
    {
        return 'manage-reviews';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        $pending = Review::query()->where('status', ReviewStatus::Pending->value)->count();

        $published = Review::query()
            ->where('status', ReviewStatus::Published->value)
            ->whereBetween('published_at', [$from, $to])
            ->count();

        // A rejected review is not touched again, so its last update is the moderation date.
        $rejected = Review::query()
            ->where('status', ReviewStatus::Rejected->value)
            ->whereBetween('updated_at', [$from, $to])
            ->count();

        $ratings = Review::query()
            ->where('status', ReviewStatus::Published->value)
            ->selectRaw('rating, count(*) as total')
            ->groupBy('rating')
            ->toBase()
            ->pluck('total', 'rating');

        $count = (int) $ratings->sum();
        $average = $count > 0
            ? round($ratings->reduce(fn (int $carry, $total, $rating) => $carry + (int) $rating * (int) $total, 0) / $count, 1)
            : 0.0;

        $distribution = [];

        for ($rating = 1; $rating <= 5; $rating++) {
            $distribution[] = ['label' => $rating.' ★', 'value' => (int) ($ratings[$rating] ?? 0)];
        }

        $queue = Review::query()
            ->where('status', ReviewStatus::Pending->value)
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->limit(8)
            ->get()
            ->map(fn (Review $review) => [
                'Produit #'.$review->product_id,
                $review->rating.'/5',
                Str::limit(trim((string) ($review->title ?: $review->body)), 80),
            ])
            ->all();

        return Widgets::section(
            'Avis clients',
            'Customer reviews',
            '/avis',
            [
                Widgets::kpi('to_moderate', 'Avis à modérer', 'Reviews to moderate', $pending, Widgets::NUMBER, $pending > 0 ? Widgets::WARN : null),
                Widgets::kpi('published', 'Publiés sur la période', 'Published in the period', $published),
                Widgets::kpi('average_rating', 'Note moyenne', 'Average rating', $average),
                Widgets::kpi('rejected', 'Rejetés sur la période', 'Rejected in the period', $rejected),
            ],
            [
                Widgets::chart('ratings', 'Répartition des notes', 'Rating distribution', 'bar', $distribution),
            ],
            [
                Widgets::table('pending_reviews', 'Avis en attente', 'Reviews awaiting moderation', [
                    ['Produit', 'Product'], ['Note', 'Rating'], ['Extrait', 'Excerpt'],
                ], $queue),
            ],
        );
    }
}
