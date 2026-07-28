#!/usr/bin/env bash
# Revo-Com / Inov-Com — pull latest code and redeploy Docker stack.
#
# Run from repo root on the VPS:
#   bash deploy/docker/deploy-update.sh
#
# Optional environment variables:
#   COMPOSE_PROJECT_NAME=erp                  (default)
#   TENANT_CODE=itc                     tenant migrations (comma-separated for several)
#   SKIP_GIT_PULL=1                     skip git pull
#   SKIP_BUILD=1                        skip image rebuild (config-only change)
#   SKIP_CACHE=1                        skip config/route/view cache
#
set -euo pipefail

SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
REPO_ROOT="$(cd "$SCRIPT_DIR/../.." && pwd)"
cd "$REPO_ROOT"

COMPOSE_PROJECT_NAME="${COMPOSE_PROJECT_NAME:-erp}"
COMPOSE_FILES=(-f deploy/docker/docker-compose.prod.yml)
if [[ -f deploy/docker/docker-compose.override.yml ]]; then
  COMPOSE_FILES+=(-f deploy/docker/docker-compose.override.yml)
else
  echo "WARN: deploy/docker/docker-compose.override.yml missing — using prod compose only."
fi

dc() {
  docker compose -p "$COMPOSE_PROJECT_NAME" "${COMPOSE_FILES[@]}" "$@"
}

echo "==> Revo-Com deploy update"
echo "    Project: $COMPOSE_PROJECT_NAME"
echo "    Repo:    $REPO_ROOT"
echo

if [[ "${SKIP_GIT_PULL:-}" != "1" ]]; then
  echo "==> git pull"
  git pull --ff-only
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
    dc exec -T app php artisan tenant:migrate "$code"
  done
fi

if [[ "${SKIP_CACHE:-}" != "1" ]]; then
  echo "==> cache config / routes / views"
  dc exec -T app php artisan config:cache
  dc exec -T app php artisan route:cache
  dc exec -T app php artisan view:cache
fi

echo "==> queue:restart"
dc exec -T app php artisan queue:restart

echo
echo "Done. Check: docker compose -p $COMPOSE_PROJECT_NAME ps"
