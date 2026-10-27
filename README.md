# Technilium — Backend

API REST Laravel du projet Technilium, une marketplace de produits technologiques (Afrique francophone) et de son backoffice de gestion — catalogue, vendeurs, stocks, commandes, facturation, comptabilité, livraison, SAV.

Architecture en **monolithe modulaire** : chaque domaine métier vit sous `app/Modules/<Nom>/` avec ses propres migrations, routes, modèles, actions et tests, communiquant par événements plutôt que par jointure SQL directe.

Dépôts liés : [TECHNILIUM-WEB-STORE](https://github.com/Yamoon224/TECHNILIUM-WEB-STORE) (vitrine) · [TECHNILIUM-WEB-BACKOFFICE](https://github.com/Yamoon224/TECHNILIUM-WEB-BACKOFFICE) (équipe/vendeurs)

## Stack

- Laravel 11, PHP 8.3
- MySQL 8 (dev/prod via `docker compose`), SQLite accepté en local
- Sanctum (jetons API), Spatie Permission (rôles)
- Pest (Unit/Feature/Integration/Architecture), Larastan, Pint
- Swagger/OpenAPI généré depuis les attributs PHP

## Démarrage rapide

```bash
docker compose up -d      # MySQL, Redis, Meilisearch, Mailpit (sans Docker : adaptez DB_*/REDIS_* dans .env)
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate --seed
php artisan serve          # http://localhost:8000
```

Documentation API interactive : `php artisan l5-swagger:generate` puis http://localhost:8000/api/documentation

Compte de démonstration après le seed : `admin@technilium.app` / `password` (rôle `administrateur`), plus un petit catalogue (2 catégories, 3 produits avec offre publiée).

## Qualité

```bash
composer test          # Pest : Unit / Feature / Integration / Architecture
composer test:coverage
composer lint           # Pint (style)
composer analyse        # PHPStan/Larastan niveau 6
```

## État d'avancement

Implémenté :

- **Identity** — inscription/connexion/déconnexion par jeton Sanctum, rôles.
- **Catalog** — catégories, produits, variantes, offres multi-vendeurs avec modération, stock (palliatif en attendant le module Inventory).
- **Orders** — panier → commande en paiement à la livraison uniquement (pas de passerelle de paiement), machine à états simplifiée (pending → confirmed → shipped → delivered, ou cancelled), décrément de stock atomique et verrouillé pour éviter la survente. Communique avec Catalog via un contrat public (`Catalog\Application\Contracts\CheckoutGatewayInterface`), jamais via ses modèles Eloquent.
- **Shared** — objets-valeur `Money`, `Currency`, `Quantity`, `TauxTVA`, `Sku`, `NumeroSerie` utilisés dans tout le code.

- **Vendors, Inventory, Payments, Shipping** — candidature/modération vendeurs, mouvements de stock, paiements (COD / mobile money confirmés manuellement), expéditions.
- **Invoicing** — factures numérotées de façon séquentielle (`FAC-AAAA-000001`), TVA extraite du TTC (taux `INVOICING_VAT_PERCENT`, 18 % par défaut), documents immuables, avoir total (`AV-AAAA-000001`) pour toute correction. Endpoints `/api/v1/admin/invoices` (rôles comptable / administrateur).

Pas encore implémenté : Accounting (écritures en partie double), Payouts, Returns, Promotions, Reporting, Audit, Procurement, Customers, Notifications ; sous-commandes par vendeur, avoirs partiels, PDF des documents.

## Conventions

SOLID — contrôleurs fins, un cas d'usage = une classe `Action`, validation dans des `FormRequest`, formatage dans des `API Resource`. Montants toujours en entiers (plus petite unité monétaire), jamais en flottant. Tests d'architecture (Pest Arch) imposant les frontières entre modules.
