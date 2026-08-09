# Déploiement Bproo Platform — afroinov.com

**Un seul guide** pour déployer le monorepo `bproo-platform` et ses apps sur le VPS `kamfo-teuh-01`.

Suivez les étapes **dans l’ordre**. Ne sautez rien.

---

## 1. Ce que vous déployez

| App | Dossier | URL publique | Compose `-p` | Port local | Conteneur web |
|-----|---------|--------------|--------------|------------|---------------|
| ERP (nouvelle version) | `apps/erp` | https://myerp.afroinov.com | `myerp` | **8091** | `myerp-web-1` |
| Pharma | `apps/pharma` | https://pharma.afroinov.com | `pharma` | **8092** | `pharma-web-1` |
| Pressing | `apps/pressing` | https://pressing.afroinov.com | `pressing` | **8093** | `pressing-web-1` |
| Control Center (admin) | `apps/control-center` | https://admin.afroinov.com | `admin` | **8094** | `admin-web-1` |

### Déjà sur le VPS — **ne pas toucher**

| Élément | Notes |
|---------|--------|
| `erp.afroinov.com` | Ancienne ERP prod (`-p erp`, port 8081, `erp-web-1`) |
| storeapp / complexesms / Afro Inov | Blocs Caddy et conteneurs existants |

Vous testez la nouvelle ERP sur **myerp**. Plus tard seulement, vous pourrez repointer `erp.afroinov.com` vers cette stack.

---

## 2. Architecture données (important)

```text
                    ┌─────────────────────────────┐
                    │  Base LANDLORD (partagée)   │
                    │  bproo_landlord             │
                    │  • table tenants            │
                    │  • modules, plans, abo      │
                    │  • CRM plateforme (admin)   │
                    └─────────────┬───────────────┘
                                  │
        ┌─────────────────────────┼─────────────────────────┐
        ▼                         ▼                         ▼
 ┌──────────────┐         ┌──────────────┐         ┌──────────────┐
 │ DB entreprise│         │ DB entreprise│         │ DB entreprise│
 │ erp_demo_…   │         │ pharma_x_…   │         │ pressing_y_… │
 │ stock,vente… │         │ lots,ordo…   │         │ commandes…   │
 └──────────────┘         └──────────────┘         └──────────────┘
```

| Donnée | Où |
|--------|----|
| Liste des entreprises, modules catalogue, abonnements, prospects admin | **1 base landlord** partagée par les 4 apps |
| Stock, ventes, clients, commandes pressing, etc. | **1 base PostgreSQL par tenant** (entreprise) |

Oui : **chaque tenant a sa propre base**. Elle est créée automatiquement au provisioning (`DB_PROVISION_*`).

Les 4 apps utilisent les **mêmes** `DB_*` (landlord). C’est comme ça que **admin.afroinov.com** voit les entreprises créées depuis myerp / pharma / pressing.

Il n’y a **pas** de sync magique : c’est la même base landlord.

**Provisionnement multi-apps :** quand vous créez un tenant Pressing / Pharma / ERP depuis Control Center, admin **délègue** le job à l’app produit (`PRODUCT_*_URL` + `TENANT_PROVISION_SECRET`). C’est cette app qui a les packages et migrations du vertical.

---

## 3. Infra Docker (ne pas diverger)

| Élément | Valeur |
|---------|--------|
| Clone monorepo | `/home/kamfo-teuh-01/apps/bproo-platform` |
| Réseau DB | `bproo-net` |
| Conteneur PostgreSQL | `vps-db-bproo_pg` |
| Base landlord | `bproo_landlord` |
| User app | `bproo_app` |
| Secret provision | `TENANT_PROVISION_SECRET` (identique sur admin + myerp + pharma + pressing) |
| Réseau Caddy | `proxy_net` (existant) |
| Proxy | `vps-proxy-caddy-1` |
| Caddyfile | `/home/kamfo-teuh-01/vps-proxy/Caddyfile` |
| Build Docker | racine monorepo → `deployment/docker/Dockerfile.app` + `packages/` |

Chaque app a sa propre stack : `app` + `web` + `queue` + `scheduler` + `redis`.

---

## 4. Prérequis (avant SSH)

1. [ ] DNS prêts vers l’IP du VPS :
   - `myerp.afroinov.com`
   - `pharma.afroinov.com`
   - `pressing.afroinov.com`
   - `admin.afroinov.com`
2. [ ] Monorepo poussé sur Git (URL de clone + branche)
3. [ ] Accès SSH au VPS
4. [ ] 2 mots de passe forts prêts (Postgres admin + user app)

Remplacez dans tout le guide :

```text
GIT_REPO_URL  → URL réelle du dépôt bproo-platform
GIT_BRANCH    → branche (ex. main)
```

---

## 5. Étape 0 — SSH + secrets

```bash
ssh kamfo-teuh-01
```

Gardez **ce terminal ouvert** jusqu’à la fin :

```bash
export BPROO_DB_ADMIN_PASS='REMPLACER_mot_de_passe_postgres_fort'
export BPROO_DB_APP_PASS='REMPLACER_mot_de_passe_app_fort'
export TENANT_PROVISION_SECRET="$(openssl rand -hex 32)"
export DEMO_TENANT_CODE='demo'
export GIT_REPO_URL='https://github.com/VOUS/bproo-platform.git'
export GIT_BRANCH='main'

echo "TENANT_PROVISION_SECRET=$TENANT_PROVISION_SECRET"   # à garder : même valeur sur les 4 apps
```

Vérifier :

```bash
echo "admin_pass len: ${#BPROO_DB_ADMIN_PASS}"
echo "app_pass len:   ${#BPROO_DB_APP_PASS}"
echo "git: ${GIT_REPO_URL} @ ${GIT_BRANCH}"
```

Ports libres (ne pas utiliser 8081 = ERP prod) :

```bash
ss -tln | grep -E '809[1-4]' || echo "OK: ports 8091–8094 libres"

docker ps --format 'table {{.Names}}\t{{.Status}}' | grep -E 'erp-|storeapp|complexesms|caddy' || true
```

**Ne pas** arrêter `erp-web-1` / la stack prod.

---

## 6. Étape 1 — Cloner le monorepo

```bash
mkdir -p /home/kamfo-teuh-01/apps
cd /home/kamfo-teuh-01/apps

if [ -d bproo-platform/.git ]; then
  cd bproo-platform
  git fetch origin
  git checkout "${GIT_BRANCH}"
  git pull --ff-only
else
  git clone -b "${GIT_BRANCH}" "${GIT_REPO_URL}" bproo-platform
  cd bproo-platform
fi

pwd
ls apps/erp apps/pharma apps/pressing apps/control-center
ls deployment/docker/Dockerfile.app packages/platform
```

Attendu : tous les `ls` réussissent.

---

## 7. Étape 2 — PostgreSQL landlord (une seule fois)

Une instance Postgres pour **toute** la plateforme Bproo. Les bases **par tenant** seront créées dedans plus tard.

```bash
docker network create bproo-net 2>/dev/null || true

docker run -d \
  --name vps-db-bproo_pg \
  --network bproo-net \
  --restart unless-stopped \
  -e POSTGRES_USER=postgres \
  -e POSTGRES_PASSWORD="${BPROO_DB_ADMIN_PASS}" \
  -v vps-db-bproo_pg-data:/var/lib/postgresql/data \
  postgres:17-alpine

until docker exec vps-db-bproo_pg pg_isready -U postgres; do sleep 2; done
echo "PostgreSQL OK"
```

Créer user + base landlord :

```bash
docker exec -i vps-db-bproo_pg psql -U postgres <<SQL
DO \$\$
BEGIN
  IF NOT EXISTS (SELECT FROM pg_roles WHERE rolname = 'bproo_app') THEN
    CREATE USER bproo_app WITH PASSWORD '${BPROO_DB_APP_PASS}' CREATEDB;
  END IF;
END
\$\$;

SELECT 'CREATE DATABASE bproo_landlord OWNER bproo_app'
WHERE NOT EXISTS (SELECT FROM pg_database WHERE datname = 'bproo_landlord')\gexec

GRANT ALL PRIVILEGES ON DATABASE bproo_landlord TO bproo_app;
\c bproo_landlord
GRANT ALL ON SCHEMA public TO bproo_app;
SQL

docker exec -i vps-db-bproo_pg psql -U postgres -c "\l bproo_landlord"
```

Vérifier le réseau Caddy :

```bash
docker network ls | grep proxy_net
docker inspect vps-proxy-caddy-1 --format '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}'
```

Si le nom n’est pas `proxy_net`, notez-le et remplacez-le partout ci-dessous.

---

## 8. Helper — fonction compose (à coller une fois)

Dans le même terminal SSH :

```bash
bproo_dc() {
  local project="$1"; shift
  local appdir="$1"; shift
  docker compose -p "$project" \
    -f "/home/kamfo-teuh-01/apps/bproo-platform/apps/${appdir}/deploy/docker/docker-compose.prod.yml" \
    -f "/home/kamfo-teuh-01/apps/bproo-platform/apps/${appdir}/deploy/docker/docker-compose.override.yml" \
    "$@"
}
```

Exemples plus bas : `bproo_dc myerp erp ps`, `bproo_dc admin control-center logs -f app`.

---

## 9. Étape 3 — Déployer ERP (myerp) en premier

Le premier host crée le schéma landlord (migrate + seed).

### 9.1 Env + override

```bash
cd /home/kamfo-teuh-01/apps/bproo-platform/apps/erp

cat > deploy/docker/.env.production <<EOF
APP_NAME="Bproo ERP"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://myerp.afroinov.com
FORCE_HTTPS=true

APP_PRODUCT_KEY=erp
TENANT_PROVISION_SECRET=${TENANT_PROVISION_SECRET}
PRODUCT_ERP_URL=https://myerp.afroinov.com
PRODUCT_PHARMA_URL=https://pharma.afroinov.com
PRODUCT_PRESSING_URL=https://pressing.afroinov.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=vps-db-bproo_pg
DB_PORT=5432
DB_DATABASE=bproo_landlord
DB_USERNAME=bproo_app
DB_PASSWORD=${BPROO_DB_APP_PASS}

DB_PROVISION_HOST=vps-db-bproo_pg
DB_PROVISION_PORT=5432
DB_PROVISION_DATABASE=postgres
DB_PROVISION_USERNAME=postgres
DB_PROVISION_PASSWORD=${BPROO_DB_ADMIN_PASS}

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_QUEUE=default

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@afroinov.com
MAIL_FROM_NAME="\${APP_NAME}"

HTTP_PORT=8091
EOF
chmod 600 deploy/docker/.env.production

cat > deploy/docker/docker-compose.override.yml <<'EOF'
services:
  app:
    networks: [default, bproo-net]
  queue:
    networks: [default, bproo-net]
  scheduler:
    networks: [default, bproo-net]
  web:
    ports:
      - "127.0.0.1:8091:80"
    networks: [default, proxy_net]

networks:
  bproo-net:
    external: true
  proxy_net:
    external: true
EOF
```

### 9.2 Build, clé, démarrage

```bash
cd /home/kamfo-teuh-01/apps/bproo-platform/apps/erp

bproo_dc myerp erp build --no-cache

# Bypass entrypoint noise; keep only the key line
APP_KEY=$(bproo_dc myerp erp run --rm --entrypoint php app artisan key:generate --show | tr -d '\r' | tail -n1)
echo "APP_KEY=${APP_KEY}"
# Use | delimiter — keys contain + / =
sed -i "s|^APP_KEY=.*$|APP_KEY=${APP_KEY}|" deploy/docker/.env.production
grep '^APP_KEY=base64:' deploy/docker/.env.production

bproo_dc myerp erp up -d
bproo_dc myerp erp ps

docker network connect bproo-net myerp-app-1 2>/dev/null || true
docker network connect bproo-net myerp-queue-1 2>/dev/null || true
docker network connect bproo-net myerp-scheduler-1 2>/dev/null || true
docker network connect proxy_net myerp-web-1 2>/dev/null || true

curl -sI http://127.0.0.1:8091/ | head -5
# Simple DB check (do NOT use db:show — it may try to install doctrine/dbal interactively)
bproo_dc myerp erp exec app php artisan migrate:status
```

### 9.3 Migrations + seed

```bash
bproo_dc myerp erp exec app php artisan migrate --force
bproo_dc myerp erp exec app php artisan db:seed --force
bproo_dc myerp erp exec app php artisan modules:sync
bproo_dc myerp erp exec app php artisan config:cache
bproo_dc myerp erp exec app php artisan route:cache
bproo_dc myerp erp exec app php artisan view:cache
```

Admin seed (changez le mot de passe dès la 1ʳᵉ connexion) :

- URL : `https://myerp.afroinov.com/admin` (après Caddy, étape 13)
- Identifiants typiques : `admin@demo.invo` / `password`

---

## 10. Étape 4 — Control Center (admin.afroinov.com)

**Même** landlord DB. Les entreprises myerp apparaîtront ici.

```bash
cd /home/kamfo-teuh-01/apps/bproo-platform/apps/control-center

cat > deploy/docker/.env.production <<EOF
APP_NAME="Bproo Control Center"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://admin.afroinov.com
FORCE_HTTPS=true

APP_PRODUCT_KEY=control-center
TENANT_PROVISION_SECRET=${TENANT_PROVISION_SECRET}

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=vps-db-bproo_pg
DB_PORT=5432
DB_DATABASE=bproo_landlord
DB_USERNAME=bproo_app
DB_PASSWORD=${BPROO_DB_APP_PASS}

DB_PROVISION_HOST=vps-db-bproo_pg
DB_PROVISION_PORT=5432
DB_PROVISION_DATABASE=postgres
DB_PROVISION_USERNAME=postgres
DB_PROVISION_PASSWORD=${BPROO_DB_ADMIN_PASS}

PRODUCT_ERP_URL=https://myerp.afroinov.com
PRODUCT_PHARMA_URL=https://pharma.afroinov.com
PRODUCT_PRESSING_URL=https://pressing.afroinov.com

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_QUEUE=default

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@afroinov.com
MAIL_FROM_NAME="\${APP_NAME}"

HTTP_PORT=8094
EOF
chmod 600 deploy/docker/.env.production

cat > deploy/docker/docker-compose.override.yml <<'EOF'
services:
  app:
    networks: [default, bproo-net]
  queue:
    networks: [default, bproo-net]
  scheduler:
    networks: [default, bproo-net]
  web:
    ports:
      - "127.0.0.1:8094:80"
    networks: [default, proxy_net]

networks:
  bproo-net:
    external: true
  proxy_net:
    external: true
EOF

bproo_dc admin control-center build --no-cache

APP_KEY=$(bproo_dc admin control-center run --rm --entrypoint php app artisan key:generate --show | tr -d '\r' | tail -n1)
echo "APP_KEY=${APP_KEY}"
sed -i "s|^APP_KEY=.*$|APP_KEY=${APP_KEY}|" deploy/docker/.env.production
grep '^APP_KEY=base64:' deploy/docker/.env.production

bproo_dc admin control-center up -d
bproo_dc admin control-center ps

docker network connect bproo-net admin-app-1 2>/dev/null || true
docker network connect bproo-net admin-queue-1 2>/dev/null || true
docker network connect bproo-net admin-scheduler-1 2>/dev/null || true
docker network connect proxy_net admin-web-1 2>/dev/null || true

bproo_dc admin control-center exec app php artisan migrate --force
bproo_dc admin control-center exec app php artisan modules:sync
bproo_dc admin control-center exec app php artisan config:cache
bproo_dc admin control-center exec app php artisan route:cache
bproo_dc admin control-center exec app php artisan view:cache

curl -sI http://127.0.0.1:8094/ | head -5
```

Login admin : `https://admin.afroinov.com/admin` (après Caddy).  
Même users landlord que myerp (même DB).

---

## 11. Étape 5 — Pharma (one-shot)

Prérequis : `bproo-net` + Postgres landlord déjà créés (étapes 2–3), Caddy avec le bloc `pharma.afroinov.com`.

```bash
cd /home/kamfo-teuh-01/apps/bproo-platform
git pull --ff-only

cd apps/pharma

# Même DB_* que myerp / admin (landlord partagé)
cat > deploy/docker/.env.production <<EOF
APP_NAME="Bproo Pharma"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://pharma.afroinov.com
FORCE_HTTPS=true

APP_PRODUCT_KEY=pharma
TENANT_PROVISION_SECRET=${TENANT_PROVISION_SECRET}
PRODUCT_ERP_URL=https://myerp.afroinov.com
PRODUCT_PHARMA_URL=https://pharma.afroinov.com
PRODUCT_PRESSING_URL=https://pressing.afroinov.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=vps-db-bproo_pg
DB_PORT=5432
DB_DATABASE=bproo_landlord
DB_USERNAME=bproo_app
DB_PASSWORD=${BPROO_DB_APP_PASS}

DB_PROVISION_HOST=vps-db-bproo_pg
DB_PROVISION_PORT=5432
DB_PROVISION_DATABASE=postgres
DB_PROVISION_USERNAME=postgres
DB_PROVISION_PASSWORD=${BPROO_DB_ADMIN_PASS}

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_QUEUE=default

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@afroinov.com
MAIL_FROM_NAME="\${APP_NAME}"

HTTP_PORT=8092
EOF
chmod 600 deploy/docker/.env.production

# Build + APP_KEY + réseaux + migrate + caches (tout-en-un)
COMPOSE_PROJECT=pharma HTTP_PORT=8092 bash deploy/docker/bootstrap-prod.sh

curl -sI https://pharma.afroinov.com/ | head -8
```

---

## 12. Étape 6 — Pressing (one-shot)

```bash
cd /home/kamfo-teuh-01/apps/bproo-platform
git pull --ff-only

cd apps/pressing

cat > deploy/docker/.env.production <<EOF
APP_NAME="Bproo Pressing"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://pressing.afroinov.com
FORCE_HTTPS=true

APP_PRODUCT_KEY=pressing
TENANT_PROVISION_SECRET=${TENANT_PROVISION_SECRET}
PRODUCT_ERP_URL=https://myerp.afroinov.com
PRODUCT_PHARMA_URL=https://pharma.afroinov.com
PRODUCT_PRESSING_URL=https://pressing.afroinov.com

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=vps-db-bproo_pg
DB_PORT=5432
DB_DATABASE=bproo_landlord
DB_USERNAME=bproo_app
DB_PASSWORD=${BPROO_DB_APP_PASS}

DB_PROVISION_HOST=vps-db-bproo_pg
DB_PROVISION_PORT=5432
DB_PROVISION_DATABASE=postgres
DB_PROVISION_USERNAME=postgres
DB_PROVISION_PASSWORD=${BPROO_DB_ADMIN_PASS}

CACHE_DRIVER=redis
SESSION_DRIVER=redis
QUEUE_CONNECTION=redis
REDIS_HOST=redis
REDIS_PASSWORD=null
REDIS_PORT=6379
REDIS_QUEUE=default

MAIL_MAILER=smtp
MAIL_HOST=smtp.example.com
MAIL_PORT=587
MAIL_USERNAME=
MAIL_PASSWORD=
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@afroinov.com
MAIL_FROM_NAME="\${APP_NAME}"

HTTP_PORT=8093
EOF
chmod 600 deploy/docker/.env.production

COMPOSE_PROJECT=pressing HTTP_PORT=8093 bash deploy/docker/bootstrap-prod.sh

curl -sI https://pressing.afroinov.com/ | head -8
```

---

## 13. Étape 7 — Caddy (HTTPS public)

### 13.1 Brancher les webs au proxy

```bash
docker network connect proxy_net myerp-web-1 2>/dev/null || true
docker network connect proxy_net pharma-web-1 2>/dev/null || true
docker network connect proxy_net pressing-web-1 2>/dev/null || true
docker network connect proxy_net admin-web-1 2>/dev/null || true

docker exec vps-proxy-caddy-1 wget -qO- --timeout=5 http://myerp-web-1/ 2>&1 | head -c 80; echo
docker exec vps-proxy-caddy-1 wget -qO- --timeout=5 http://admin-web-1/ 2>&1 | head -c 80; echo
```

### 13.2 Ajouter les blocs (sans modifier erp / storeapp / complexesms)

```bash
sudo nano /home/kamfo-teuh-01/vps-proxy/Caddyfile
```

**À la fin du fichier**, ajoutez :

```caddy
myerp.afroinov.com {
    encode gzip zstd
    reverse_proxy myerp-web-1:80
}

pharma.afroinov.com {
    encode gzip zstd
    reverse_proxy pharma-web-1:80
}

pressing.afroinov.com {
    encode gzip zstd
    reverse_proxy pressing-web-1:80
}

admin.afroinov.com {
    encode gzip zstd
    reverse_proxy admin-web-1:80
}
```

Recharger :

```bash
docker exec vps-proxy-caddy-1 caddy reload --config /etc/caddy/Caddyfile
```

### 13.3 Tests

```bash
curl -sI https://myerp.afroinov.com/ | head -8
curl -sI https://pharma.afroinov.com/ | head -8
curl -sI https://pressing.afroinov.com/ | head -8
curl -sI https://admin.afroinov.com/ | head -8
curl -sI https://erp.afroinov.com/ | head -8
```

Attendu : les 4 nouveaux en `200`/`302` ; **erp prod toujours OK**.

Un **502** → `docker network connect proxy_net <app>-web-1` puis retester.

---

## 14. Étape 8 — Créer un tenant (entreprise) + sa propre DB

Exemple : entreprise ERP de test.

1. Ouvrir `https://admin.afroinov.com/admin` (ou `https://myerp.afroinov.com/admin`)
2. Créer une entreprise type **ERP / POS**, code = `demo` (ou `${DEMO_TENANT_CODE}`)
3. Ne pas remplir manuellement host/user/password DB
4. Attendre **Santé OK** (1–2 min) — Postgres crée une base du type `erp_demo_xxxx`
5. Puis sur le host produit concerné :

```bash
# Tenant ERP → migrations depuis myerp
bproo_dc myerp erp exec app php artisan tenant:migrate "${DEMO_TENANT_CODE}"
bproo_dc myerp erp exec app php artisan modules:sync
```

Login vendeur :

```text
https://myerp.afroinov.com/app/login?tenant=demo
```

Pour Pharma / Pressing : créer l’entreprise avec le bon **type** depuis Control Center.
Le provisionnement est **délégué** automatiquement vers l’app produit (`PRODUCT_*_URL` + `TENANT_PROVISION_SECRET`).
Les modules / migrations du vertical s’installent sur **ce** host (plus besoin de `tenant:migrate` manuel si le secret est configuré).

Relance manuelle (Santé) ou réparation d’un ancien tenant :

```bash
bproo_dc pharma pharma exec app php artisan tenant:migrate CODE
bproo_dc pressing pressing exec app php artisan tenant:migrate CODE
```

> `tenant:migrate` ne couvre que les migrations core / `tenant_modules` déjà publiées.
> Pour (ré)installer les modules pressing/pharma : utiliser « Modules » dans admin, ou `InstallModuleJob` sur le host produit.
Vérifier qu’une base tenant a bien été créée :

```bash
docker exec -i vps-db-bproo_pg psql -U postgres -c "\l" | grep -E 'erp_|pharma_|pressing_'
```

---

## 15. Checklist finale

- [ ] `https://erp.afroinov.com` (ancienne prod) toujours OK
- [ ] `https://myerp.afroinov.com` OK
- [ ] `https://pharma.afroinov.com` OK
- [ ] `https://pressing.afroinov.com` OK
- [ ] `https://admin.afroinov.com` OK — voit les entreprises landlord
- [ ] « Ouvrir l’app » depuis admin → myerp / pharma / pressing
- [ ] Au moins 1 tenant avec **sa propre DB** + login `/app/login?tenant=`
- [ ] `APP_DEBUG=false` partout
- [ ] Mot de passe seed changé
- [ ] storeapp / complexesms intacts

```bash
docker ps --format 'table {{.Names}}\t{{.Status}}' | grep -E 'myerp-|pharma-|pressing-|admin-|erp-|caddy|storeapp|complexesms'
```

---

## 16. Mises à jour (une app à la fois)

Après `git push` du monorepo :

```bash
# ERP / myerp
cd /home/kamfo-teuh-01/apps/bproo-platform/apps/erp
COMPOSE_PROJECT_NAME=myerp TENANT_CODE=demo bash deploy/docker/deploy-update.sh

# Admin
cd /home/kamfo-teuh-01/apps/bproo-platform/apps/control-center
COMPOSE_PROJECT_NAME=admin bash deploy/docker/deploy-update.sh

# Pharma
cd /home/kamfo-teuh-01/apps/bproo-platform/apps/pharma
COMPOSE_PROJECT_NAME=pharma bash deploy/docker/deploy-update.sh

# Pressing
cd /home/kamfo-teuh-01/apps/bproo-platform/apps/pressing
COMPOSE_PROJECT_NAME=pressing bash deploy/docker/deploy-update.sh
```

Le script fait `git pull` à la racine monorepo, rebuild **cette** app, migrate, cache.

Si vous changez un **package** partagé (`packages/inovcom/...`), rebuild **chaque** app qui l’utilise.

---

## 17. Dépannage

| Problème | Action |
|----------|--------|
| 502 | `docker network connect proxy_net <projet>-web-1` |
| Admin sans entreprises | Vérifier les **mêmes** `DB_*` sur toutes les apps |
| Mauvaise URL « Ouvrir l’app » | `PRODUCT_*_URL` sur admin + `config:cache` |
| DB inaccessible | `docker network connect bproo-net <projet>-app-1` |
| Build / packages | `ls /home/kamfo-teuh-01/apps/bproo-platform/packages` |
| Conflit avec ancienne ERP | Ne jamais utiliser `-p erp`, port 8081, `vps-db-erp_pg` |
| Certificat TLS | `docker logs vps-proxy-caddy-1 --tail 50` |
| Logs | `bproo_dc myerp erp logs -f app` |

---

## 18. Plus tard — bascule erp.afroinov.com

Quand myerp est validé :

1. Fenêtre de maintenance
2. Décider migration données / cutover Caddy
3. Pointer `erp.afroinov.com` vers la nouvelle stack
4. Mettre à jour `PRODUCT_ERP_URL` sur admin
5. Garder l’ancienne stack arrêtée un temps (rollback)

Jusque-là : **prod = erp**, **nouvelle plateforme = myerp + pharma + pressing + admin**.
