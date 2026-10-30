<?php

declare(strict_types=1);

namespace App\Modules\Reporting\Application\Dashboard;

use App\Modules\Shared\Dashboard\DashboardSection;
use Carbon\CarbonInterface;
use Closure;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The back-office home page: every registered block (see {@see DashboardSection})
 * the account may use, the ones of its own profile first.
 *
 * Authorisation is by feature, not by role: a custom group, or a built-in profile
 * whose features the super admin changed, gets exactly the blocks of the features
 * it holds. The profile only decides the order.
 */
final class DashboardService
{
    /** Blocks shown first for each built-in profile, in this order; the rest follow in the default order. */
    private const PRIORITY = [
        'super_admin' => ['sales', 'finance', 'alerts', 'orders', 'access', 'audit'],
        'administrateur' => ['sales', 'finance', 'alerts', 'orders', 'internal-sales', 'procurement'],
        'gestionnaire_catalogue' => ['catalog', 'stock', 'vendors', 'promotions'],
        'gestionnaire_stock' => ['stock', 'procurement', 'catalog'],
        'operateur_commandes' => ['orders', 'shipments', 'payments', 'returns'],
        'vendeur_interne' => ['internal-sales', 'orders', 'stock'],
        'responsable_achats' => ['procurement', 'stock', 'catalog'],
        'comptable' => ['finance', 'payments', 'invoices', 'payouts', 'accounting'],
        'logistique' => ['shipments', 'orders', 'returns'],
        'support' => ['support', 'returns', 'reviews', 'notifications', 'orders'],
        'marketing' => ['promotions', 'reviews', 'sales', 'catalog'],
    ];

    /** Order of the blocks beyond a profile's own. */
    private const DEFAULT_ORDER = [
        'sales', 'finance', 'alerts', 'orders', 'internal-sales', 'payments', 'shipments', 'returns', 'invoices',
        'catalog', 'stock', 'procurement', 'vendors', 'promotions', 'payouts', 'accounting',
        'support', 'reviews', 'notifications', 'audit', 'access',
    ];

    /**
     * @param  iterable<DashboardSection>  $sections
     */
    public function __construct(private readonly iterable $sections) {}

    /**
     * @param  list<string>  $roles  role slugs of the account
     * @param  Closure(string): bool  $can  whether the account holds a feature
     * @return list<array<string, mixed>>
     */
    public function sectionsFor(array $roles, Closure $can, CarbonInterface $from, CarbonInterface $to): array
    {
        $allowed = [];
        foreach ($this->sections as $section) {
            if ($can($section->ability())) {
                $allowed[$section->key()] = $section;
            }
        }

        $blocks = [];
        foreach ($this->ordered(array_keys($allowed), $roles) as $key) {
            $section = $allowed[$key];

            try {
                $blocks[] = ['key' => $key, 'ability' => $section->ability(), ...$section->build($from, $to)];
            } catch (Throwable $e) {
                // One failing block must not take the whole home page down.
                Log::error('Dashboard block failed', ['section' => $key, 'error' => $e->getMessage()]);
                $blocks[] = ['key' => $key, 'ability' => $section->ability(), 'title' => $key, 'title_en' => $key, 'link' => '/', 'error' => true, 'kpis' => [], 'charts' => [], 'tables' => []];
            }
        }

        return $blocks;
    }

    /**
     * @param  list<string>  $keys
     * @param  list<string>  $roles
     * @return list<string>
     */
    private function ordered(array $keys, array $roles): array
    {
        $wanted = [];
        foreach ($roles as $role) {
            array_push($wanted, ...(self::PRIORITY[$role] ?? []));
        }
        array_push($wanted, ...self::DEFAULT_ORDER);

        // Known keys in priority order, then any block a module added that is not listed.
        $ordered = array_values(array_unique(array_filter($wanted, fn (string $key) => in_array($key, $keys, true))));

        return [...$ordered, ...array_values(array_diff($keys, $ordered))];
    }
}
