#!/usr/bin/env bash
# One-shot production bootstrap for a Bproo app (pharma / pressing / myerp / admin).
#
# Run from the app directory, e.g.:
#   cd /home/kamfo-teuh-01/apps/bproo-platform/apps/pharma
#   COMPOSE_PROJECT=pharma HTTP_PORT=8092 bash deploy/docker/bootstrap-prod.sh
#
# Required env:
#   COMPOSE_PROJECT   myerp|pharma|pressing|admin
#   HTTP_PORT         8091|8092|8093|8094
#
# Optional:
#   SKIP_BUILD=1
#   RUN_SEED=1          (default 0 — only first landlord host needs seed)
#   RUN_MIGRATE=1       (default 1)
#   BPROO_NET=bproo-net
#   PROXY_NET=proxy_net
#
set -euo pipefail

APP_ROOT="$(cd "$(dirname "${BASH_SOURCE[0]}")/../.." && pwd)"
cd "$APP_ROOT"

COMPOSE_PROJECT="${COMPOSE_PROJECT:-}"
HTTP_PORT="${HTTP_PORT:-}"
BPROO_NET="${BPROO_NET:-bproo-net}"
PROXY_NET="${PROXY_NET:-proxy_net}"
RUN_MIGRATE="${RUN_MIGRATE:-1}"
RUN_SEED="${RUN_SEED:-0}"

if [[ -z "$COMPOSE_PROJECT" || -z "$HTTP_PORT" ]]; then
  echo "Usage: COMPOSE_PROJECT=pharma HTTP_PORT=8092 bash deploy/docker/bootstrap-prod.sh"
  exit 1
fi

if [[ ! -f deploy/docker/.env.production ]]; then
  echo "ERROR: deploy/docker/.env.production missing. Create it first (see docs/deploy/MULTI_APP_AFROINOV.md)."
  exit 1
fi

# Ensure override with networks + localhost port
cat > deploy/docker/docker-compose.override.yml <<EOF
services:
  app:
    networks: [default, ${BPROO_NET}]
  queue:
    networks: [default, ${BPROO_NET}]
  scheduler:
    networks: [default, ${BPROO_NET}]
  web:
    ports:
      - "127.0.0.1:${HTTP_PORT}:80"
    networks: [default, ${PROXY_NET}]

networks:
  ${BPROO_NET}:
    external: true
  ${PROXY_NET}:
    external: true
EOF

dc() {
  docker compose -p "$COMPOSE_PROJECT" \
    -f deploy/docker/docker-compose.prod.yml \
    -f deploy/docker/docker-compose.override.yml \
    "$@"
}

echo "==> Project: $COMPOSE_PROJECT  port: $HTTP_PORT  app: $APP_ROOT"

docker network create "$BPROO_NET" 2>/dev/null || true
# proxy_net must already exist (Caddy). Fail clearly if not.
if ! docker network inspect "$PROXY_NET" >/dev/null 2>&1; then
  echo "ERROR: Docker network $PROXY_NET not found (Caddy). Create/join it first."
  exit 1
fi

if [[ "${SKIP_BUILD:-}" != "1" ]]; then
  echo "==> Building images"
  dc build --no-cache
fi

# APP_KEY (bypass entrypoint noise)
current_key="$(grep -E '^APP_KEY=' deploy/docker/.env.production | head -n1 | cut -d= -f2- || true)"
if [[ -z "$current_key" || "$current_key" == "null" ]]; then
  echo "==> Generating APP_KEY"
  APP_KEY="$(dc run --rm --entrypoint php app artisan key:generate --show | tr -d '\r' | tail -n1)"
  if [[ "$APP_KEY" != base64:* ]]; then
    echo "ERROR: failed to generate APP_KEY (got: ${APP_KEY})"
    exit 1
  fi
  sed -i "s|^APP_KEY=.*$|APP_KEY=${APP_KEY}|" deploy/docker/.env.production
  grep '^APP_KEY=base64:' deploy/docker/.env.production
else
  echo "==> APP_KEY already set"
fi

echo "==> Starting stack"
dc up -d --force-recreate --remove-orphans
dc ps

# Belt-and-suspenders network attach (names: <project>-app-1, …)
for svc in app queue scheduler; do
  name="${COMPOSE_PROJECT}-${svc}-1"
  docker network connect "$BPROO_NET" "$name" 2>/dev/null || true
done
docker network connect "$PROXY_NET" "${COMPOSE_PROJECT}-web-1" 2>/dev/null || true

echo "==> Waiting for app"
sleep 5

if [[ "$RUN_MIGRATE" == "1" ]]; then
  echo "==> migrate --force"
  dc exec -T app php artisan migrate --force
fi

if [[ "$RUN_SEED" == "1" ]]; then
  echo "==> db:seed --force"
  dc exec -T app php artisan db:seed --force || true
  dc exec -T app php artisan modules:sync || true
fi

dc exec -T app php artisan config:cache || true
dc exec -T app php artisan route:cache || true
dc exec -T app php artisan view:cache || true

echo "==> Local HTTP check :${HTTP_PORT}"
curl -sI "http://127.0.0.1:${HTTP_PORT}/" | head -8 || true

echo
echo "Done. Ensure Caddy has a block for this host → ${COMPOSE_PROJECT}-web-1:80"
echo "Then: docker exec vps-proxy-caddy-1 caddy reload --config /etc/caddy/Caddyfile"
