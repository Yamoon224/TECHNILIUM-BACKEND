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

- **Accounting** — grand livre en écriture seule (partie double, SYSCOHADA : 411, 571, 585, 4431, 701), écritures générées de façon synchrone et idempotente par les événements `InvoiceIssued` / `PaymentSettled` (facture, avoir, encaissement, remboursement). Lecture : `/api/v1/admin/accounting/entries` et `/trial-balance`.
- **Payouts** — reversements vendeurs : ventes livrées hors délai de retour (`PAYOUTS_RETURN_DELAY_DAYS`, 7 j) moins commission (`PAYOUTS_COMMISSION_PERCENT`, 10 %), une commande n'est jamais reversée deux fois, écritures 701 → 706 / 4011 puis 4011 → 585. Les lignes de commande portent le `vendor_id` de l'offre (base des sous-commandes par vendeur) et la commande a un `delivered_at`. Endpoints `/api/v1/admin/payouts`.
- **Returns** — dossiers RMA (`requested → accepted → received → refunded`, refus possible avant acceptation ou après contrôle) sur commande livrée, dans le délai `RETURNS_WINDOW_DAYS` (7 j), montant plafonné à ce qui reste remboursable. Un remboursement émet un avoir (partiel) sur la facture vivante via `ReturnRefunded`, et réduit proportionnellement le reversement du vendeur ; un retour ouvert bloque le reversement. Les avoirs peuvent désormais être partiels (plusieurs par facture, plafonnés au solde). Endpoints `/api/v1/admin/returns`.
- **Espace vendeur** (`/api/v1/vendors/me/*`, module Vendors) — synthèse, commandes (uniquement les lignes du vendeur et sa part), relevés de reversement, offres. Le vendeur est toujours déduit du compte authentifié (jamais d'un paramètre) ; un reversement d'un autre vendeur répond 404. Lecture seule hors offres : les commandes n'ont pas encore de statuts par vendeur.

Pas encore implémenté : écritures manuelles, journaux achats/banque, états de synthèse, Payouts, Returns, Promotions, Reporting, Audit, Procurement, Customers, Notifications ; sous-commandes par vendeur avec statuts propres (seul le `vendor_id` par ligne existe), remboursement effectif du client (Payments ne gère que le remboursement total d'un paiement), remise en stock des retours, régularisation d'un remboursement postérieur au reversement, échange/réparation/garanties par numéro de série, PDF des documents.

## Conventions

SOLID — contrôleurs fins, un cas d'usage = une classe `Action`, validation dans des `FormRequest`, formatage dans des `API Resource`. Montants toujours en entiers (plus petite unité monétaire), jamais en flottant. Tests d'architecture (Pest Arch) imposant les frontières entre modules.
