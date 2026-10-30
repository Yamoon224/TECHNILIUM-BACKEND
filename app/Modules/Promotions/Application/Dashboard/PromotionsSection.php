<?php

declare(strict_types=1);

namespace App\Modules\Promotions\Application\Dashboard;

use App\Modules\Promotions\Domain\CouponType;
use App\Modules\Promotions\Infrastructure\Models\Announcement;
use App\Modules\Promotions\Infrastructure\Models\Coupon;
use App\Modules\Promotions\Infrastructure\Models\CouponRedemption;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;

/**
 * Promotions block of the back-office home page: coupons and flash announcements.
 */
final class PromotionsSection implements DashboardSection
{
    public function key(): string
    {
        return 'promotions';
    }

    public function ability(): string
    {
        return 'manage-promotions';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        // Active now: enabled, inside its period, not exhausted.
        $usable = fn (): Builder => Coupon::query()
            ->where('active', true)
            ->where(fn (Builder $q) => $q->whereNull('starts_at')->orWhere('starts_at', '<=', now()))
            ->where(fn (Builder $q) => $q->whereNull('ends_at')->orWhere('ends_at', '>', now()))
            ->where(fn (Builder $q) => $q->whereNull('max_uses')->orWhereColumn('used_count', '<', 'max_uses'));

        $activeCoupons = $usable()->count();
        $redemptions = CouponRedemption::query()->whereBetween('created_at', [$from, $to])->count();

        $showing = Announcement::query()->showing()->count();
        $scheduled = Announcement::query()
            ->where('is_active', true)
            ->whereNull('archived_at')
            ->where('starts_at', '>', now())
            ->count();

        $rows = $usable()
            ->whereNotNull('ends_at')
            ->orderBy('ends_at')
            ->limit(8)
            ->get()
            ->map(fn (Coupon $c) => [
                $c->code,
                $c->type === CouponType::Percent ? $c->value.' %' : $c->value.' XOF',
                $c->ends_at?->format('d/m/Y'),
                $c->max_uses === null ? (string) $c->used_count : $c->used_count.' / '.$c->max_uses,
            ])->all();

        return Widgets::section(
            'Promotions',
            'Promotions',
            '/promotions',
            [
                Widgets::kpi('coupons_active', 'Codes promo actifs', 'Active promo codes', $activeCoupons, Widgets::NUMBER, Widgets::GOOD),
                Widgets::kpi('coupon_redemptions', 'Utilisations de codes (période)', 'Code redemptions (period)', $redemptions),
                Widgets::kpi('announcements_showing', 'Annonces flash en cours', 'Flash announcements showing', $showing),
                Widgets::kpi('announcements_scheduled', 'Annonces programmées', 'Scheduled announcements', $scheduled),
            ],
            [],
            [
                Widgets::table('coupons_expiring', 'Codes promo qui expirent bientôt', 'Promo codes expiring soon', [
                    ['Code', 'Code'],
                    ['Remise', 'Discount'],
                    ['Fin', 'Ends'],
                    ['Utilisations', 'Uses'],
                ], $rows),
            ],
        );
    }
}
