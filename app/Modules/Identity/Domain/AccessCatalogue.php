<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * Every back-office feature that can be granted to a group and switched on or off for a user or a
 * group. A feature is named after the ability that guards its API routes (`can:manage-orders`).
 * The built-in profiles of the cahier §4 keep the access listed here; custom groups get whatever
 * the super admin ticks.
 */
final class AccessCatalogue
{
    public const MANAGE_ACCESS = 'manage-access';

    /**
     * @return array<string, array{label: string, label_en: string, group: string, group_en: string, roles: list<Role>}>
     */
    public static function features(): array
    {
        return [
            'manage-orders' => self::feature('Commandes', 'Orders', 'Ventes', 'Sales', [Role::OperateurCommandes]),
            'manage-internal-sales' => self::feature('Ventes internes (caisse)', 'Internal sales (till)', 'Ventes', 'Sales', [Role::OperateurCommandes, Role::Comptable]),
            'manage-payments' => self::feature('Paiements', 'Payments', 'Ventes', 'Sales', [Role::Comptable]),
            'manage-shipments' => self::feature('Livraisons', 'Deliveries', 'Ventes', 'Sales', [Role::Logistique]),
            'manage-invoices' => self::feature('Factures', 'Invoices', 'Ventes', 'Sales', [Role::Comptable]),
            'manage-returns' => self::feature('Retours', 'Returns', 'Ventes', 'Sales', [Role::Support]),
            'manage-promotions' => self::feature('Promotions', 'Promotions', 'Ventes', 'Sales', [Role::Marketing]),
            'manage-catalog' => self::feature('Catalogue (catégories, produits, modération)', 'Catalogue (categories, products, moderation)', 'Catalogue', 'Catalogue', [Role::GestionnaireCatalogue]),
            'manage-stock' => self::feature('Stock', 'Stock', 'Catalogue', 'Catalogue', [Role::GestionnaireStock]),
            'manage-vendors' => self::feature('Vendeurs', 'Vendors', 'Catalogue', 'Catalogue', []),
            'manage-procurement' => self::feature('Achats et approvisionnements', 'Purchasing and supplies', 'Finance', 'Finance', [Role::ResponsableAchats]),
            'manage-accounting' => self::feature('Comptabilité', 'Accounting', 'Finance', 'Finance', [Role::Comptable]),
            'manage-payouts' => self::feature('Reversements', 'Payouts', 'Finance', 'Finance', [Role::Comptable]),
            'manage-support' => self::feature('Support', 'Support', 'Clients', 'Customers', [Role::Support]),
            'manage-reviews' => self::feature('Avis clients', 'Customer reviews', 'Clients', 'Customers', [Role::Support, Role::Marketing]),
            'manage-notifications' => self::feature('Notifications', 'Notifications', 'Clients', 'Customers', [Role::Support]),
            'view-reports' => self::feature('Rapports', 'Reports', 'Analyse', 'Insight', [Role::Comptable]),
            'view-audit' => self::feature("Journal d'audit", 'Audit log', 'Analyse', 'Insight', []),
            'generate-qrcodes' => self::feature('Codes QR', 'QR codes', 'Outils', 'Tools', [
                Role::GestionnaireCatalogue, Role::GestionnaireStock, Role::OperateurCommandes, Role::Comptable, Role::ResponsableAchats,
            ]),
            'be-vendor' => self::feature('Espace vendeur', 'Vendor space', 'Vendeurs tiers', 'Third-party vendors', [Role::VendeurTiers], administrateur: false),
        ];
    }

    /** @return list<string> */
    public static function keys(): array
    {
        return array_keys(self::features());
    }

    public static function has(string $ability): bool
    {
        return array_key_exists($ability, self::features());
    }

    /**
     * Roles that hold a feature by default: the listed profiles, plus the administrator.
     *
     * @return list<string>
     */
    public static function builtinRolesFor(string $ability): array
    {
        $feature = self::features()[$ability] ?? null;

        return $feature === null ? [] : array_map(fn (Role $role) => $role->value, $feature['roles']);
    }

    /**
     * @param  list<Role>  $roles
     * @return array{label: string, label_en: string, group: string, group_en: string, roles: list<Role>}
     */
    private static function feature(string $label, string $labelEn, string $group, string $groupEn, array $roles, bool $administrateur = true): array
    {
        return [
            'label' => $label,
            'label_en' => $labelEn,
            'group' => $group,
            'group_en' => $groupEn,
            'roles' => $administrateur ? [...$roles, Role::Administrateur] : $roles,
        ];
    }
}
