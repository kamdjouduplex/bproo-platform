# Phase 4 — Guide de référence

> **Période :** Semaines 13 à 18  
> **Statut :** ✅ Complété  
> **Cible :** BPROO ERP — plateforme multi-tenant Laravel 10 / Livewire 4

---

## Sommaire

1. [Rapports avancés — Rentabilité & Techniciens](#1-rapports-avancés--rentabilité--techniciens)
2. [PWA / Mobile-first techniciens](#2-pwa--mobile-first-techniciens) _(à venir)_
3. [Maturité plateforme](#3-maturité-plateforme) _(à venir)_

---

## 1. Rapports avancés — Rentabilité & Techniciens

**Statut :** ✅ Complété (Semaines 13–14)  
**Composant :** `app/Livewire/Tenant/Reports.php`  
**Vue :** `resources/views/livewire/tenant/reports.blade.php`

Le composant `Reports` existant (3 onglets : AR Aging, Revenus, Pipeline devis) a été étendu avec deux nouveaux onglets sans créer de nouveaux fichiers.

### Nouvel onglet — Rentabilité projets (`$tab = 'rentabilite'`)

**Données :** `rentabiliteData()` — retourne une collection de projets non annulés.

| Colonne calculée | Formule |
|------------------|---------|
| `budget` | `projects.budget` |
| `actual_cost` | `projects.actual_cost` |
| `billed` | `SUM(invoices.total_ttc)` WHERE `project_id = p.id` AND `status != 'cancelled'` |
| `collected` | `SUM(invoices.amount_paid)` WHERE `project_id = p.id` AND `status = 'paid'` |
| `margin` | `collected - actual_cost` |
| `margin_pct` | `margin / collected * 100` (null si collected = 0) |
| `over_budget` | `actual_cost > budget` |

**Affichage :**
- Barre KPI : Budget total, Coût réel, Facturé, Encaissé, Marge globale
- Tableau par projet : code/titre, client, statut badge, barre d'avancement, budget (rouge si dépassé ⚠), coût réel, encaissé, marge (vert/rouge), marge %
- Total en pied de tableau
- Export CSV (`exportRentabilite()`) : 10 colonnes

**Requête :** LEFT JOIN sur sous-requête agrégée depuis `invoices` (pas de chargement Eloquent cross-package).

### Nouvel onglet — Rapport techniciens (`$tab = 'technicien'`)

**Données :** `technicienData()` — retourne une collection par technicien (filtrée par `$year`).

| Colonne calculée | Formule |
|------------------|---------|
| `total` | `COUNT(interventions)` |
| `done` | `COUNT` WHERE `status = 'done'` |
| `total_minutes` | `SUM(duration_minutes)` WHERE `status = 'done'` |
| `avg_minutes` | `total_minutes / done` |
| `sla_breached` | `COUNT` WHERE `done_at > maintenance_orders.due_at` |
| `sla_rate` | `sla_breached / total * 100` |

**Affichage :**
- Filtre année (réutilise `$year` partagé avec l'onglet Devis)
- Barre KPI : Techniciens actifs, Interventions, Réalisées, Heures terrain, Taux hors SLA (coloré rouge si > 20%)
- Tableau : technicien, total, réalisées, barre taux réalisation, durée totale, durée moyenne (affichée en Xh YY ou N min), hors SLA (rouge si > 0), taux SLA (badge vert/orange/rouge)
- Total en pied de tableau
- Export CSV (`exportTechnicien()`) : 7 colonnes

**Jointures :** `interventions → users` (tenant DB) + `interventions → maintenance_orders` pour lire `due_at` (SLA deadline).

### Règle technique établie en Phase 4

**Jointures cross-table dans le même tenant :** toujours utiliser `DB::connection('tenant')` avec un seul query builder. Ne pas mélanger deux connexions dans une même requête.

```php
// ✅ Correct — tout sur la connexion tenant
$db = DB::connection('tenant');
$rows = $db->table('interventions as i')
    ->join('users as u', ...)
    ->join('maintenance_orders as mo', ...)
    ->get();
```

---

## 2. PWA / Mobile-first techniciens

> ⏳ **À implémenter — Semaines 15–16**

### Périmètre prévu

| Fonctionnalité | Description |
|----------------|-------------|
| Service Worker | Cache offline des vues terrain |
| Manifest PWA | Installable sur mobile |
| Vue mobile intervention | Interface simplifiée pour technicien sur chantier |
| Signature digitale mobile | Canvas touch pour signature client |

---

## 3. Maturité plateforme

**Statut :** ✅ Complété (Semaines 17–18)

### 3.1 — Suivi abonnements (subscription tracking)

**Migration :** `database/migrations/2026_04_06_000001_add_subscription_to_tenants_table.php`

Nouveaux champs sur la table `tenants` :

| Colonne | Type | Description |
|---------|------|-------------|
| `plan` | `string(20)` | `free` · `starter` · `pro` · `enterprise` — défaut `free` |
| `plan_started_at` | `timestamp nullable` | Date de début du plan actuel |
| `plan_expires_at` | `timestamp nullable` | Date d'expiration du plan payant |
| `trial_ends_at` | `timestamp nullable` | Fin de la période d'essai |

**Helpers du modèle `Tenant` :**

```php
$t->planLabel()       // → 'Gratuit', 'Starter', 'Pro', 'Enterprise'
$t->planBadgeClass()  // → 'badge-free', 'badge-starter', 'badge-pro', 'badge-enterprise'
$t->isTrialing()      // → true si trial_ends_at est dans le futur
$t->isExpired()       // → true si plan_expires_at est dans le passé
```

**Formulaire tenant** : section "Abonnement" ajoutée dans `tenant-form.blade.php` — sélecteur plan + date expiration + date fin essai.

**Liste tenants** : colonne Plan avec badge coloré + indicateur "expiré" si plan_expires_at passé.

### 3.2 — Analytique plateforme

**Route :** `system.analytics` (`/admin/analytics`)  
**Composant :** `app/Livewire/Admin/Analytics.php`  
**Vue :** `resources/views/livewire/admin/analytics.blade.php`  
**Sidebar :** icône `chart`, lien "Analytique" — admin seulement

**Métriques affichées :**

| Bloc | Contenu |
|------|---------|
| KPI cards | Total entreprises, actives, provisionnées, inactives, échecs |
| Répartition abonnements | Barre horizontale par plan (free/starter/pro/enterprise) |
| Modules les plus adoptés | Top 8 modules par nombre de tenants activateurs |
| Entreprises créées / mois | Mini bar chart — 6 derniers mois |
| Dernières entreprises | Tableau : nom, code, plan badge, statut provisionnement, date création |

---

## Récapitulatif des fichiers créés / modifiés en Phase 4

### Semaines 13–14 (Rapports avancés)

```
app/Livewire/Tenant/Reports.php                    ← +rentabiliteData(), +technicienData(), +exportRentabilite(), +exportTechnicien()
resources/views/livewire/tenant/reports.blade.php  ← +2 onglets, +CSS rp-badge, rp-prog-*
```

### Semaines 17–18 (Maturité plateforme)

```
database/migrations/2026_04_06_000001_add_subscription_to_tenants_table.php  ← nouveau
app/Models/Tenant.php                              ← +plan fields fillable/casts, +planLabel(), +planBadgeClass(), +isTrialing(), +isExpired()
app/Livewire/Admin/Analytics.php                   ← nouveau composant admin
resources/views/livewire/admin/analytics.blade.php ← nouvelle vue
app/Livewire/Admin/TenantForm.php                  ← +plan, plan_expires_at, trial_ends_at (properties + validation)
resources/views/livewire/admin/tenant-form.blade.php ← +section Abonnement
resources/views/livewire/admin/tenants.blade.php   ← +colonne Plan avec badge coloré
routes/web.php                                     ← +Route system.analytics
resources/views/layouts/app.blade.php              ← +lien Analytique dans sidebar admin
```

### Commande à exécuter

```bash
php artisan migrate
```
