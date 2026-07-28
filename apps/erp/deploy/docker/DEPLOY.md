# Déploiement production — erp.afroinov.com

Guide **copier-coller** pour le VPS `kamfo-teuh-01`.  
Exécutez les étapes **dans l’ordre**, en SSH sur le serveur.

---

## Récap (déjà fixé — ne pas changer)


| Élément                  | Valeur                                          |
| ------------------------ | ----------------------------------------------- |
| Chemin projet            | `/home/kamfo-teuh-01/apps/erp`                  |
| URL publique             | `https://erp.afroinov.com`                      |
| Port local (Caddy → app) | **8081**                                        |
| Projet Docker Compose    | `erp`                                           |
| Réseau Docker DB         | `erp-net`                                       |
| Conteneur PostgreSQL     | `vps-db-erp_pg`                                 |
| Base système             | `erp_system`                                    |
| Utilisateur DB app       | `erp_app`                                       |
| Branche Git              | `trams-negoce`                                  |
| Dépôt                    | `https://github.com/kamdjouduplex/inov-com.git` |


**Prérequis DNS :** enregistrement `erp.afroinov.com` → IP du VPS (déjà fait).

**Apps existantes** (`storeapp`, `complexesms`) : **ne pas modifier** leurs blocs Caddy.

---

## Étape 0 — 3 variables à choisir (2 minutes)

Connectez-vous en SSH, puis :

```bash
export ERP_DB_ADMIN_PASS='PASS_postres254##$$'
export ERP_DB_APP_PASS='PASS_$$ERP_54788##&&P'
export ERP_TENANT_CODE='demo'
```

Gardez ce terminal ouvert (ou recopiez ces 3 lignes si vous ouvrez une nouvelle session).

---

## Étape 1 — Vérifier que le port 8081 est libre

```bash
ss -tln | grep 8081 || echo "OK: port 8081 libre"
```

Si une ligne s’affiche (port déjà pris), **arrêtez** et changez de port avant de continuer.

---

## Étape 2 — Cloner le code

```bash
mkdir -p /home/kamfo-teuh-01/apps
cd /home/kamfo-teuh-01/apps

git clone -b trams-negoce https://github.com/kamdjouduplex/inov-com.git erp
cd /home/kamfo-teuh-01/apps/erp

ls -la Dockerfile deploy/docker/docker-compose.prod.yml
```

---

## Étape 3 — PostgreSQL dédié ERP

### 3a. Créer le conteneur

```bash
docker network create erp-net 2>/dev/null || true

docker run -d \
  --name vps-db-erp_pg \
  --network erp-net \
  --restart unless-stopped \
  -e POSTGRES_USER=postgres \
  -e POSTGRES_PASSWORD="${ERP_DB_ADMIN_PASS}" \
  -v vps-db-erp_pg-data:/var/lib/postgresql/data \
  postgres:17-alpine
```

Attendre que Postgres soit prêt :

```bash
until docker exec vps-db-erp_pg pg_isready -U postgres; do sleep 2; done
echo "PostgreSQL OK"
```

### 3b. Créer la base et l’utilisateur app

```bash
docker exec -i vps-db-erp_pg psql -U postgres <<SQL
CREATE USER erp_app WITH PASSWORD '${ERP_DB_APP_PASS}';
CREATE DATABASE erp_system OWNER erp_app;
GRANT ALL PRIVILEGES ON DATABASE erp_system TO erp_app;
\c erp_system
GRANT ALL ON SCHEMA public TO erp_app;
SQL
```

---

## Étape 4 — Fichier `.env.production`

```bash
cd /home/kamfo-teuh-01/apps/erp

cp deploy/docker/.env.production.example deploy/docker/.env.production
chmod 600 deploy/docker/.env.production
```

Générer le fichier (mot de passe DB injecté automatiquement) :

```bash
cat > deploy/docker/.env.production <<EOF
APP_NAME="BprooDev ERP"
APP_ENV=production
APP_KEY=
APP_DEBUG=false
APP_URL=https://erp.afroinov.com
FORCE_HTTPS=true

LOG_CHANNEL=stack
LOG_LEVEL=warning

DB_CONNECTION=pgsql
DB_HOST=vps-db-erp_pg
DB_PORT=5432
DB_DATABASE=erp_system
DB_USERNAME=erp_app
DB_PASSWORD=${ERP_DB_APP_PASS}

# Création auto des bases vendeurs (compte postgres — une seule fois à l'install)
DB_PROVISION_HOST=vps-db-erp_pg
DB_PROVISION_PORT=5432
DB_PROVISION_DATABASE=postgres
DB_PROVISION_USERNAME=postgres
DB_PROVISION_PASSWORD=${ERP_DB_ADMIN_PASS}

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

HTTP_PORT=8081
EOF

chmod 600 deploy/docker/.env.production
```

*(SMTP : modifiez plus tard avec `nano deploy/docker/.env.production` si vous envoyez des e-mails.)*

---

## Étape 5 — Override Docker (port 8081 localhost)

```bash
cat > deploy/docker/docker-compose.override.yml <<'EOF'
services:
  web:
    ports:
      - "127.0.0.1:8081:80"
EOF
```

---

## Étape 6 — Build, clé APP_KEY, démarrage

```bash
cd /home/kamfo-teuh-01/apps/erp

docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  build --no-cache
```

Générer `APP_KEY` :

```bash
APP_KEY=$(docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  run --rm app php artisan key:generate --show)

echo "APP_KEY=${APP_KEY}"
```

Coller la clé dans le fichier : 

```bash
sed -i "s|^APP_KEY=.*|APP_KEY=${APP_KEY}|" deploy/docker/.env.production
grep APP_KEY deploy/docker/.env.production
```

Démarrer la stack :

```bash
docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  up -d

docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  ps
```

Test local :

```bash
curl -sI http://127.0.0.1:8081/ | head -5
```

---

## Étape 7 — Relier l’app à PostgreSQL

```bash
docker network connect erp-net erp-app-1
docker network connect erp-net erp-queue-1
docker network connect erp-net erp-scheduler-1
```

*(Si les noms diffèrent : `docker compose -p erp ps` → colonne **NAMES**.)*

---

## Étape 8 — Migrations base système + cache

```bash
cd /home/kamfo-teuh-01/apps/erp

docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  exec app php artisan migrate --force

docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  exec app php artisan db:seed --force

docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  exec app php artisan config:cache

docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  exec app php artisan route:cache

docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  exec app php artisan view:cache
```

**Compte admin plateforme** (créé par le seed) :

- URL : `https://erp.afroinov.com/admin`
- E-mail : `admin@demo.invo`
- Mot de passe : `password` → **changez-le immédiatement** après la première connexion.

---

## Étape 9 — Tenant

### 9a. Créer le tenant dans l’admin (sans commande serveur)

Prérequis : `DB_PROVISION_*` renseigné dans `.env.production` (étape 4 — mot de passe postgres admin).

**Dans le formulaire « Créer vendeur » :**

- Renseigner nom, code, admin tenant (nom, e-mail, mot de passe)
- La base PostgreSQL est créée automatiquement (`erp_{code}_xxxx`, ex. `erp_demo_k7m2`)
- **Ne pas remplir** DB Host / Username / Password
- L’**e-mail admin tenant** (`admin@…`) n’est **pas** l’utilisateur PostgreSQL

1. Ouvrir `https://erp.afroinov.com/admin/tenants/create`
2. Créer le vendeur
3. Ouvrir **Santé vendeurs** et attendre le statut **OK** (1–2 min)
4. En cas d’échec, cliquer **Relancer** sur la même page (pas de SSH)

*(Optionnel — diagnostic queue : `docker compose -p erp … logs -f queue`)*

### 9b. Migrations tenant

```bash
cd /home/kamfo-teuh-01/apps/erp

docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  exec app php artisan tenant:migrate "${ERP_TENANT_CODE}"

docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  exec app php artisan modules:sync
```

Activer les modules souhaités et les permissions dans l’admin tenant.

---

## Étape 10 — Caddy (HTTPS public)

Sur ce VPS, Caddy (`vps-proxy-caddy-1`) joint les apps **par nom de conteneur Docker** (ex. `erp-web-1`), pas via `127.0.0.1`.

Caddyfile : **`/home/kamfo-teuh-01/vps-proxy/Caddyfile`**

### 10a. Brancher `erp-web-1` au réseau du proxy

Trouver le réseau partagé avec Caddy (sur ce VPS : **`proxy_net`**) :

```bash
docker inspect erp-web-1 --format '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}'
docker inspect vps-proxy-caddy-1 --format '{{range $k, $v := .NetworkSettings.Networks}}{{$k}}{{"\n"}}{{end}}'
```

Connecter le conteneur web ERP (remplacez le nom de réseau si besoin) :

```bash
docker network connect proxy_net erp-web-1
```

Vérifier depuis Caddy :

```bash
docker exec vps-proxy-caddy-1 wget -qO- --timeout=3 http://erp-web-1/ 2>&1 | head -3
```

*(Doit afficher `<!DOCTYPE html>` — sinon le réseau ou le nom du conteneur est incorrect.)*

### 10b. Bloc Caddy

Éditez `/home/kamfo-teuh-01/vps-proxy/Caddyfile` et **ajoutez** (sans toucher storeapp / complexesms) :

```caddy
erp.afroinov.com {
    encode gzip zstd
    reverse_proxy erp-web-1:80
}
```

Recharger :

```bash
docker exec vps-proxy-caddy-1 caddy reload --config /etc/caddy/Caddyfile
```

Vérifier :

```bash
curl -sI https://erp.afroinov.com/ | head -8
```

Attendu : **`HTTP/2 200`** ou **`302`**. Un **502** = refaire l’étape 10a (`docker network connect`).

*(Le port `8081` sur localhost sert aux tests directs ; Caddy utilise le réseau Docker interne.)*

---

## Étape 11 — Contrôles finaux

```bash
# Autres apps intactes
docker ps --filter name=storeapp --filter name=complexesms

# Stack ERP
docker compose -p erp \
  -f /home/kamfo-teuh-01/apps/erp/deploy/docker/docker-compose.prod.yml \
  -f /home/kamfo-teuh-01/apps/erp/deploy/docker/docker-compose.override.yml \
  ps

docker compose -p erp \
  -f deploy/docker/docker-compose.prod.yml \
  -f deploy/docker/docker-compose.override.yml \
  logs --tail=50 app queue
```

Checklist :

- [ ] `https://erp.afroinov.com` répond (certificat TLS OK)
- [ ] `https://erp.afroinov.com/admin` — connexion admin OK
- [ ] Tenant `itc` (ou votre code) — connexion utilisateur OK
- [ ] `APP_DEBUG=false` dans `.env.production`
- [ ] Mot de passe admin changé

---

## Mises à jour (après chaque release)

**Sur votre PC :** commit + `git push`.

**Sur le VPS — une commande :**

```bash
cd /home/kamfo-teuh-01/apps/erp
COMPOSE_PROJECT_NAME=erp TENANT_CODE=itc bash deploy/docker/deploy-update.sh
```

*(Remplacez `itc` par votre code tenant.)*

---

## Dépannage rapide


| Problème                    | Action                                                                                                                                                  |
| --------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------------------- |
| 502 sur erp.afroinov.com    | `docker network connect proxy_net erp-web-1` puis `docker exec vps-proxy-caddy-1 wget -qO- http://erp-web-1/ \| head -3` |
| `Bind for 0.0.0.0:8080 failed` | Supprimer le bloc `ports:` sous `web` dans `docker-compose.prod.yml` (ne garder que l’override `127.0.0.1:8081:80`), puis relancer `up -d`          |
| **500** + `Uninitialized string offset 0` | Rebuild conteneur `web` (nginx envoie un `X-Forwarded-Host` vide) — voir section ci-dessous |
| **500** + `Uninitialized string offset 0` dans `Request.php` | Nginx envoie un `X-Forwarded-Host` vide. Rebuild `web` après correction nginx (`git pull` + `docker compose ... build web --no-cache && up -d`) |
| DB inaccessible             | `docker network connect erp-net erp-app-1`                                                                                                              |
| Certificat TLS              | `docker logs vps-proxy-caddy-1 --tail 50`                                                                                                               |
| Liens HTTP au lieu de HTTPS | vérifier `APP_URL` + `FORCE_HTTPS=true` + `php artisan config:cache`                                                                                    |
| Logs Laravel                | `docker compose -p erp -f deploy/docker/docker-compose.prod.yml -f deploy/docker/docker-compose.override.yml exec app tail -f storage/logs/laravel.log` |


---

## Architecture

```text
Internet → vps-proxy-caddy-1 (443)
              ├─ storeapp / complexesms — inchangés
              └─ erp.afroinov.com → erp-web-1:80 (réseau proxy_net)

erp-app / erp-queue / erp-scheduler → erp-redis
                                   → vps-db-erp_pg (réseau erp-net)
```

---

## Alias pratique (optionnel)

Pour éviter de retaper les longues commandes compose :

```bash
echo 'alias erp-dc="docker compose -p erp -f /home/kamfo-teuh-01/apps/erp/deploy/docker/docker-compose.prod.yml -f /home/kamfo-teuh-01/apps/erp/deploy/docker/docker-compose.override.yml"' >> ~/.bashrc
source ~/.bashrc
```

Ensuite : `erp-dc ps`, `erp-dc logs -f app`, etc.