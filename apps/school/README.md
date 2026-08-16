# Bproo School

Application école de la plateforme Bproo. Host mince (`apps/school`) + packages partagés `inovcom/*` / `platform/*` + vertical `packages/verticals/school` (`bproo/school`).

## Architecture

```
apps/school                 → host Laravel (UI, config, tenancy)
packages/verticals/school   → modules métier (élèves, notes, paiements, cartes ID…)
packages/platform/*         → tenancy, admin, modules
packages/inovcom/*          → briques partagées (users, configuration…)
```

Type de tenant Control Center : **`school`**.  
URL locale par défaut : `http://127.0.0.1:8000` (`PRODUCT_SCHOOL_URL`).  
Prod beta : `https://school.afroinov.com` (port **8095**).

## Pack modules V1 (activés par défaut pour un tenant `school`)

| Module | Rôle |
|---|---|
| `school` | Hub / tableau de bord |
| `school_years` | Années académiques (+ carry-over) |
| `school_classes` / `school_subjects` / `school_teachers` | Référentiel |
| `school_students` / `school_enrollments` | Élèves & inscriptions |
| `school_id_cards` | Cartes ID (QR, lot 12/A4) |
| `school_fees` / `school_payments` | Frais, paiements, soldes |
| `school_exams` / `school_grading` | Examens & barèmes |
| `school_publications` / `school_report_cards` | Publication & bulletins |
| `school_notifications` | Journal SMS/email (channels optionnels) |
| `school_settings` | Listes, langues, audit |
| `users` / `configuration` | Core tenant |

Les modules commerce / pharmacie restent dans le host mais **ne s’activent pas** pour un tenant `school`.

## Démarrage local

```bash
cd apps/school
cp .env.example .env
# Aligner DB landlord + APP_URL=http://127.0.0.1:8000 + APP_PRODUCT_KEY=school
composer install
php artisan key:generate
php artisan storage:link
php artisan modules:sync
npm install && npm run build
php artisan serve
```

Créer une société de type **Bproo School** depuis le Control Center (`PRODUCT_SCHOOL_URL`).

### Seed démo (optionnel)

```bash
php artisan tenant:seed-school-demo school
```

Comptes typiques (voir seeder) : `directeur.demo@school.test` / `Directeur#2025`.

## Beta — limites connues

- SMS / email : non requis ; sans config les notifs sont journalisées en `skipped`.
- Cartes ID : QR oui, code-barres (rendu) plus tard.
- IDs élèves : motif fixe `SCH-{année}-{####}` pour l’instant.
- Emplois du temps / présences : hors scope beta.
- UI principalement en français (switcher locale présent, traductions school partielles).

## Déploiement

Voir `deploy/docker/DEPLOY.md` et le guide multi-apps `docs/deploy/MULTI_APP_AFROINOV.md`.

```bash
cd /home/kamfo-teuh-01/apps/bproo-platform
git pull --ff-only
cd apps/school
COMPOSE_PROJECT=school HTTP_PORT=8095 bash deploy/docker/bootstrap-prod.sh
```

PRD : `Bproo_School_PRD_v1.1.md`.
