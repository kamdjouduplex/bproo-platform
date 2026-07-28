# Bproo Platform

Modular multi-tenant Laravel ecosystem (ERP, Pressing, BAT) migrating toward a shared package architecture.

## Layout

```
bproo-platform/
├── apps/
│   ├── erp/          # ERP (+ POS capability) — was bproo-erp / bproo-erp-pos
│   ├── pressing/     # Pressing vertical
│   └── bat/          # Construction (BAT)
├── packages/         # Shared packages (populated in Phase M2+)
├── docs/             # Architecture & migration docs
├── tools/            # Fingerprint & automation
├── backups/          # Git bundles from pre-monorepo repos
└── .github/
```

## Docs

Start at [`docs/README.md`](docs/README.md).

- Official architecture: `docs/BPROO_PLATFORM_ARCHITECTURE_v1.md`
- Migration roadmap: `docs/MIGRATION_ROADMAP.md`
- Current phase: `docs/PHASE_1_STATUS.md`

## Run an app (local)

Each product remains a normal Laravel app. From that app directory:

```bash
cd apps/erp        # or apps/pressing, apps/bat
composer install
cp .env.example .env   # if needed
php artisan key:generate
php artisan serve
```

Deploy pipelines should set the working directory to the relevant `apps/*` folder. **No package unification yet** (Phase M2).

## Freeze

Do not duplicate `packages/inovcom/*` across apps — see `docs/FREEZE_POLICY.md`.

## History

Pre-monorepo git history is preserved in `backups/git-bundles/*.bundle` and recorded in `backups/pre-monorepo-git-remotes.csv`.
