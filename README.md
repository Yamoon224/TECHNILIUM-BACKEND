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

Implémenté : **Identity** (inscription/connexion/déconnexion par jeton, rôles), **Catalog** (catégories, produits, variantes, offres multi-vendeurs avec modération). **Shared** fournit les objets-valeur `Money`, `Currency`, `Quantity`, `TauxTVA`, `Sku`, `NumeroSerie` utilisés dans tout le code.

Pas encore implémenté (dossiers créés sous `app/Modules/` mais vides) : Vendors, Inventory, Cart/Orders, Payments, Invoicing, Accounting, Payouts, Shipping, Returns, Promotions, Reporting, Audit.

## Conventions

SOLID — contrôleurs fins, un cas d'usage = une classe `Action`, validation dans des `FormRequest`, formatage dans des `API Resource`. Montants toujours en entiers (plus petite unité monétaire), jamais en flottant. Tests d'architecture (Pest Arch) imposant les frontières entre modules.
