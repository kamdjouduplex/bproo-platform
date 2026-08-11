# Déploiement Docker

**Guide unique :** [`docs/deploy/MULTI_APP_AFROINOV.md`](../../../../docs/deploy/MULTI_APP_AFROINOV.md)

Cette app : **https://school.afroinov.com** (`-p school`, port **8095**).

### One-shot (après `.env.production` créé)

```bash
cd /home/kamfo-teuh-01/apps/bproo-platform
git pull --ff-only
cd apps/school
COMPOSE_PROJECT=school HTTP_PORT=8095 bash deploy/docker/bootstrap-prod.sh
```

Le script gère : build, APP_KEY, réseaux `bproo-net` + `proxy_net`, migrate, caches.
