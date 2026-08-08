#!/usr/bin/env bash
# Pull monorepo + rebuild one Bproo app stack.
#
# Run from the app directory on the VPS, e.g.:
#   cd /home/kamfo-teuh-01/apps/bproo-platform/apps/erp
#   TENANT_CODE=demo bash deploy/docker/deploy-update.sh
#   # → compose project myerp (erp folder maps to myerp)
#
# Optional:
#   COMPOSE_PROJECT_NAME=myerp|pharma|pressing|admin
#   TENANT_CODE=code1,code2
#   SKIP_GIT_PULL=1
#   SKIP_BUILD=1
#   SKIP_CACHE=1
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
APP_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
MONOREPO_ROOT="$(cd "$APP_ROOT/../.." && pwd)"
cd "$APP_ROOT"

COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-$(basename "$APP_ROOT")}"
# Map folder names to compose project names used on the VPS
if [[ "$COMPOSE_PROJECT_NAME" == "control-center" ]]; then
  COMPOSE_PROJECT_NAME=admin
fi
if [[ "$COMPOSE_PROJECT_NAME" == "erp" ]]; then
  # Avoid colliding with legacy prod stack `-p erp`
  COMPOSE_PROJECT_NAME=myerp
fi

COMPOSE_FILES=(-f deploy/docker/docker-compose.prod.yml)
if [[ -f deploy/docker/docker-compose.override.yml ]]; then
  COMPOSE_FILES+=(-f deploy/docker/docker-compose.override.yml)
else
  echo "WARN: deploy/docker/docker-compose.override.yml missing — using prod compose only."
fi

dc() {
  docker compose -p "$COMPOSE_PROJECT_NAME" "${COMPOSE_FILES[@]}" "$@"
}

echo "==> Bproo deploy update"
echo "    Project:  $COMPOSE_PROJECT_NAME"
echo "    App:      $APP_ROOT"
echo "    Monorepo: $MONOREPO_ROOT"
echo

if [[ "${SKIP_GIT_PULL:-}" != "1" ]]; then
  echo "==> git pull (monorepo)"
  git -C "$MONOREPO_ROOT" pull --ff-only
  echo
fi

if [[ "${SKIP_BUILD:-}" != "1" ]]; then
  echo "==> docker compose up -d --build"
  dc up -d --build
else
  echo "==> docker compose up -d (no rebuild)"
  dc up -d
fi
echo

echo "==> php artisan migrate --force"
dc exec -T app php artisan migrate --force

if [[ -n "${TENANT_CODE:-}" ]]; then
  IFS=',' read -ra TENANTS <<< "$TENANT_CODE"
  for code in "${TENANTS[@]}"; do
    code="$(echo "$code" | xargs)"
    [[ -z "$code" ]] && continue
    echo "==> php artisan tenant:migrate $code"
    dc exec -T app php artisan tenant:migrate "$code" || true
  done
fi

if [[ "${SKIP_CACHE:-}" != "1" ]]; then
  echo "==> config/route/view cache"
  dc exec -T app php artisan config:cache
  dc exec -T app php artisan route:cache
  dc exec -T app php artisan view:cache
fi

echo
echo "==> Done. Status:"
dc ps
