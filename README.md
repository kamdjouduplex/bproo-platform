# Bproo Platform

Modular multi-tenant Laravel ecosystem (ERP, Pharma, School, Pressing, BAT, Control Center) on a shared package architecture.

## Layout

```
bproo-platform/
├── apps/
│   ├── control-center/  # Admin / licences / tenants
│   ├── pharma/          # Pharmacie (POS, lots, ordonnances)
│   ├── school/          # École
│   ├── erp/             # ERP (+ POS)
│   ├── pressing/        # Pressing
│   └── bat/             # Construction (BAT)
├── packages/            # Shared packages (inovcom, platform, ui, verticals)
├── deployment/          # Shared Docker images
├── docs/                # Architecture & migration docs
├── tools/               # Automation (fingerprint, etc.)
└── .github/
```

## Docs

Start at [`docs/README.md`](docs/README.md).

- Official architecture: `docs/BPROO_PLATFORM_ARCHITECTURE_v1.md`
- Migration roadmap: `docs/MIGRATION_ROADMAP.md`

## Run an app (local)

Each product is a normal Laravel app. From that app directory:

```bash
cd apps/pharma        # or school, erp, pressing, bat, control-center
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan serve
```

Deploy from the relevant `apps/*` folder (see each app’s `deploy/docker/DEPLOY.md`).

## Freeze

Do not duplicate `packages/inovcom/*` across apps — see `docs/FREEZE_POLICY.md`.
