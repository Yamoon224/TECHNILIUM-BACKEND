<?php

declare(strict_types=1);

namespace App\Modules\Catalog\Application\Dashboard;

use App\Modules\Catalog\Domain\OfferStatus;
use App\Modules\Catalog\Domain\ProductStatus;
use App\Modules\Catalog\Infrastructure\Models\Banner;
use App\Modules\Catalog\Infrastructure\Models\Category;
use App\Modules\Catalog\Infrastructure\Models\Offer;
use App\Modules\Catalog\Infrastructure\Models\Product;
use App\Modules\Shared\Dashboard\DashboardSection;
use App\Modules\Shared\Dashboard\Widgets;
use Carbon\CarbonInterface;

/**
 * Catalogue block of the back-office home page: product pipeline and the offers
 * waiting for moderation. Read-only, current state (the period does not apply).
 */
final class CatalogSection implements DashboardSection
{
    public function key(): string
    {
        return 'catalog';
    }

    public function ability(): string
    {
        return 'manage-catalog';
    }

    public function build(CarbonInterface $from, CarbonInterface $to): array
    {
        /** @var array<string, int> $byStatus */
        $byStatus = Product::query()
            ->selectRaw('status, count(*) as total')
            ->groupBy('status')
            ->pluck('total', 'status')
            ->map(fn ($n) => (int) $n)
            ->all();

        $published = $byStatus[ProductStatus::Published->value] ?? 0;
        $drafts = $byStatus[ProductStatus::Draft->value] ?? 0;
        $archived = $byStatus[ProductStatus::Archived->value] ?? 0;

        $pending = Offer::query()->where('status', OfferStatus::PendingReview->value)->count();
        $withoutPhoto = Product::query()
            ->where('status', ProductStatus::Published->value)
            ->whereDoesntHave('images')
            ->count();
        $categories = Category::query()->where('is_active', true)->count();
        $banners = Banner::query()->where('is_active', true)->count();

        $oldest = Offer::query()
            ->join('product_variants', 'product_variants.id', '=', 'offers.product_variant_id')
            ->join('products', 'products.id', '=', 'product_variants.product_id')
            ->where('offers.status', OfferStatus::PendingReview->value)
            ->orderBy('offers.created_at')
            ->orderBy('offers.id')
            ->limit(8)
            ->get(['offers.vendor_id', 'offers.price_amount', 'offers.created_at', 'products.brand', 'products.name']);

        $rows = $oldest->map(fn ($offer) => [
            trim($offer->brand.' '.$offer->name),
            $offer->vendor_id === null ? 'Technilium' : 'Vendeur #'.$offer->vendor_id,
            (int) $offer->price_amount,
            $offer->created_at === null ? null : \Illuminate\Support\Carbon::parse($offer->created_at)->format('d/m/Y'),
        ])->all();

        return Widgets::section(
            'Catalogue',
            'Catalogue',
            '/produits',
            [
                Widgets::kpi('products_published', 'Produits publiés', 'Published products', $published, Widgets::NUMBER, Widgets::GOOD),
                Widgets::kpi('products_draft', 'Brouillons', 'Drafts', $drafts),
                Widgets::kpi('products_archived', 'Produits archivés', 'Archived products', $archived),
                Widgets::kpi('offers_pending', 'Offres à modérer', 'Offers awaiting moderation', $pending, Widgets::NUMBER, $pending > 0 ? Widgets::WARN : null),
                Widgets::kpi('products_no_photo', 'Produits publiés sans photo', 'Published products without photo', $withoutPhoto, Widgets::NUMBER, $withoutPhoto > 0 ? Widgets::WARN : null),
                Widgets::kpi('categories_active', 'Catégories actives', 'Active categories', $categories),
                Widgets::kpi('banners_active', 'Images de bannière actives', 'Active banner images', $banners),
            ],
            [
                Widgets::chart('products_by_status', 'Produits par statut', 'Products by status', 'donut', [
                    ['label' => 'Publiés', 'label_en' => 'Published', 'value' => $published],
                    ['label' => 'Brouillons', 'label_en' => 'Drafts', 'value' => $drafts],
                    ['label' => 'Archivés', 'label_en' => 'Archived', 'value' => $archived],
                ]),
            ],
            [
                Widgets::table('offers_pending_oldest', 'Offres en attente de modération (les plus anciennes)', 'Offers awaiting moderation (oldest)', [
                    ['Produit', 'Product'],
                    ['Vendeur', 'Seller'],
                    ['Prix (XOF)', 'Price (XOF)'],
                    ['Depuis le', 'Since'],
                ], $rows),
            ],
        );
    }
}
