# Déploiement Docker

**Guide unique :** [`docs/deploy/MULTI_APP_AFROINOV.md`](../../../../docs/deploy/MULTI_APP_AFROINOV.md)

Cette app : **https://pharma.afroinov.com** (`-p pharma`, port **8092**).

### One-shot (après `.env.production` créé)

```bash
cd /home/kamfo-teuh-01/apps/bproo-platform
git pull --ff-only
cd apps/pharma
COMPOSE_PROJECT=pharma HTTP_PORT=8092 bash deploy/docker/bootstrap-prod.sh
```

Le script gère : build, APP_KEY, réseaux `bproo-net` + `proxy_net`, migrate, caches.
