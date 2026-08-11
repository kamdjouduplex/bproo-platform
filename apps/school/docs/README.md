# Documentation Inov-Com

## Développement

| Fichier | Contenu |
|---------|---------|
| [MANUEL-UTILISATEUR.md](./MANUEL-UTILISATEUR.md) | **Manuel utilisateur complet** (admin + boutique, tous modules) |
| [GUIDE-SUPER-ADMIN.md](./GUIDE-SUPER-ADMIN.md) | Guide Super Admin (texte) |
| [pdf/GUIDE-SUPER-ADMIN.pdf](./pdf/GUIDE-SUPER-ADMIN.pdf) | **Guide Super Admin (PDF)** — prêt à imprimer / partager |
| [SETUP.md](./SETUP.md) | Installation locale (PHP, PostgreSQL, npm) |
| [DEVELOPER_GUIDE.md](./DEVELOPER_GUIDE.md) | Créer et intégrer un module |
| [architecture.md](./architecture.md) | Architecture multi-tenant et modules |
| [module-packages.md](./module-packages.md) | Structure d’un package `packages/inovcom/*` |
| [modules.md](./modules.md) | Liste des modules et entités |
| [ui-guidelines.md](./ui-guidelines.md) | Conventions UI / Livewire |
| [SUBSCRIPTION_SYSTEM.md](./SUBSCRIPTION_SYSTEM.md) | Abonnements vendeurs (admin) |
| [CONTRIBUTING.md](./CONTRIBUTING.md) | Règles de contribution |

## Production

Voir **[deploy/docker/DEPLOY.md](../deploy/docker/DEPLOY.md)** — déploiement Docker sur le VPS (`erp.afroinov.com`).

### Regénérer le PDF Super Admin

```bash
php artisan docs:super-admin-pdf
```

Fichier produit : `docs/pdf/GUIDE-SUPER-ADMIN.pdf`
