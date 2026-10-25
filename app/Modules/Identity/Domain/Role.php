<?php

declare(strict_types=1);

namespace App\Modules\Identity\Domain;

/**
 * The profiles enumerated in the cahier des charges §4. Backed by Spatie
 * laravel-permission roles of the same slug — this enum exists so the rest
 * of the codebase references roles by name, never by a magic string.
 */
enum Role: string
{
    case Administrateur = 'administrateur';
    case GestionnaireCatalogue = 'gestionnaire_catalogue';
    case GestionnaireStock = 'gestionnaire_stock';
    case OperateurCommandes = 'operateur_commandes';
    case VendeurInterne = 'vendeur_interne';
    case ResponsableAchats = 'responsable_achats';
    case Comptable = 'comptable';
    case Logistique = 'logistique';
    case Support = 'support';
    case Marketing = 'marketing';
    case VendeurTiers = 'vendeur_tiers';
    case Client = 'client';

    public function label(): string
    {
        return match ($this) {
            self::Administrateur => 'Administrateur / Direction',
            self::GestionnaireCatalogue => 'Gestionnaire catalogue',
            self::GestionnaireStock => 'Gestionnaire de stock / Magasinier',
            self::OperateurCommandes => 'Opérateur commandes',
            self::VendeurInterne => 'Vendeur interne (commercial)',
            self::ResponsableAchats => 'Responsable achats',
            self::Comptable => 'Comptable / Finance',
            self::Logistique => 'Logistique / Livreur',
            self::Support => 'Support / SAV',
            self::Marketing => 'Marketing',
            self::VendeurTiers => 'Vendeur tiers',
            self::Client => 'Client',
        };
    }

    /**
     * Roles that belong to the backoffice application (vs. the storefront client role).
     *
     * @return list<self>
     */
    public static function backofficeRoles(): array
    {
        return array_filter(self::cases(), fn (self $role) => $role !== self::Client);
    }
}
