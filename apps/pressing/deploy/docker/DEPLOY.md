# Déploiement Docker

**Guide unique :** [`docs/deploy/MULTI_APP_AFROINOV.md`](../../../../docs/deploy/MULTI_APP_AFROINOV.md)

Cette app : **https://pressing.afroinov.com** (`-p pressing`, port **8093**).

### One-shot (après `.env.production` créé)

```bash
cd /home/kamfo-teuh-01/apps/bproo-platform
git pull --ff-only
cd apps/pressing
COMPOSE_PROJECT=pressing HTTP_PORT=8093 bash deploy/docker/bootstrap-prod.sh
```

Le script gère : build, APP_KEY, réseaux `bproo-net` + `proxy_net`, migrate, caches.
