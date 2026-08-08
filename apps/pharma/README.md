# Bproo Pharma

Application spécialisée pharmacie de la plateforme Bproo. Host mince (`apps/pharma`) + packages partagés `inovcom/*` + vertical `packages/verticals/pharma`.

## Architecture

```
apps/pharma          → host Laravel (UI, config, tenancy)
packages/inovcom/*   → POS, stock, lots, achats, caisse, clients…
packages/verticals/pharma → hub, rôles pharmacien/caissier/magasinier, extensions futures
```

Type de tenant Control Center : **`pharma`** (alias legacy `pharmacy` → `pharma`).  
URL locale par défaut : `http://127.0.0.1:8003` (`PRODUCT_PHARMA_URL`).

## Pack modules V1 (activés par défaut)

| Module | Rôle |
|---|---|
| `pharma` | Hub + rôles |
| `items` | Médicaments |
| `clients` | Clients |
| `providers` | Fournisseurs |
| `sales` | POS |
| `stock` | Stock |
| `purchases` | Achats |
| `batches` | Lots / péremption |
| `prescriptions` | Ordonnances |
| `caisse` | Caisse |
| `returns` | Retours |
| `debts` | Crédit client |

## Démarrage local

```bash
cd apps/pharma
cp .env.example .env   # aligner DB landlord comme ERP + APP_URL=http://127.0.0.1:8003
# Vendor : pour le MVP, junction vers ERP (évite un composer install monorepo fragile)
#   mklink /J vendor ..\erp\vendor
composer dump-autoload
php artisan key:generate
php artisan modules:sync
npm install && npm run dev
php artisan serve --port=8003
```

Créer une société de type **Bproo Pharma** depuis le Control Center (`PRODUCT_PHARMA_URL`).

## Prochaines étapes métier

1. Bloquer la vente des lots périmés (FEFO dans `batches` / `sales`)
2. Champs médicament : DCI, forme, dosage, famille thérapeutique
3. Enforcement `requires_prescription` au POS
4. V3 : mutuelles, fidélité, livraison (dans le vertical)

Voir le canvas blueprint dans Cursor : `bproo-pharma-blueprint.canvas.tsx`.
