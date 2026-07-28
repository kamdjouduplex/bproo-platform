# Phase 2 — Guide de référence

> **Période :** Semaines 1 à 6  
> **Statut :** ✅ Complété  
> **Cible :** BPROO ERP — plateforme multi-tenant Laravel 10 / Livewire 4

---

## Sommaire

1. [Maintenance module](#1-maintenance-module)
2. [DMS module](#2-dms-module)
3. [Dashboard module](#3-dashboard-module)
4. [Reporting module](#4-reporting-module)
5. [Offer Kanban, planificateur préventif & notifications](#5-offer-kanban-planificateur-préventif--notifications)
6. [Login tenant redesign](#6-login-tenant-redesign)
7. [Règles techniques établies](#7-règles-techniques-établies)
8. [Commandes artisan utiles](#8-commandes-artisan-utiles)

---

## 1. Maintenance module

**Package :** `packages/inovcom/maintenance`  
**Clé module :** `maintenance`  
**Permissions :** `maintenance.view`, `maintenance.create`, `maintenance.edit`, `maintenance.delete`, `maintenance.dispatch`, `maintenance.close`

### Modèles

| Modèle | Table | Description |
|--------|-------|-------------|
| `MaintenanceContract` | `maintenance_contracts` | Contrats (préventif / correctif / full_service), cycle de facturation, SLA horaires |
| `MaintenanceOrder` | `maintenance_orders` | Ordres de maintenance, priorité, due_at SLA, workflow statut |
| `Intervention` | `interventions` | Interventions rattachées à un ordre, matériaux JSONB, signature client |

### Workflow des ordres

```
open → assigned → in_progress → done → closed
                              ↘ cancelled
```

### Composants Livewire

| Composant | Route | Description |
|-----------|-------|-------------|
| `ContractsIndex` | `tenant.maintenance.contracts.index` | Liste + filtres statut |
| `ContractForm` | `tenant.maintenance.contracts.create/edit` | CRUD + suspend/réactivation/expiration |
| `OrdersIndex` | `tenant.maintenance.orders.index` | Liste + filtres statut/priorité + badge SLA |
| `OrderForm` | `tenant.maintenance.orders.create/edit` | CRUD + boutons workflow + auto-fill SLA depuis contrat |
| `InterventionForm` | `tenant.maintenance.interventions.create/edit` | Lignes matériaux dynamiques, calcul durée |

### Codes générés automatiquement

- Contrats : `CTR00001`, `CTR00002`, …
- Ordres : `OM00001`, `OM00002`, …

---

## 2. DMS module

**Package :** `packages/inovcom/dms`  
**Clé module :** `dms`  
**Permissions :** `dms.view`, `dms.create`, `dms.delete`

### Modèles

| Modèle | Table | Description |
|--------|-------|-------------|
| `Document` | `documents` | Fichier stocké (titre, catégorie, mime, taille, chemin, version) |
| `DocumentAttachment` | `document_attachments` | Lien polymorphique document ↔ entité (project, maintenance_order, …) |

### Stockage

Chemin local : `storage/app/tenants/{tenantCode}/documents/{uuid}.ext`

### Composants Livewire

| Composant | Route / Usage | Description |
|-----------|---------------|-------------|
| `DocumentsIndex` | `tenant.dms.index` | Bibliothèque globale, filtre catégorie, téléchargement, suppression |
| `DocumentUpload` | `tenant.dms.upload` | Page d'upload avec drag-and-drop (`$wire.upload()`), redirection `back=` |
| `EntityDocuments` | Embarqué (`<livewire:inovcom-dms.entity-documents>`) | Panneau attachement inline, lien vers upload avec `attachable_type` + `attachable_id` |

### Intégration dans d'autres modules

```blade
<livewire:inovcom-dms.entity-documents
    attachable-type="project"
    :attachable-id="$projectId"
    :key="'docs-'.$projectId"
/>
```

Déjà intégré dans : **ProjectForm** et **OrderForm (Maintenance)**.

### Catégories disponibles

`contract` · `plan` · `permit` · `photo` · `report` · `invoice` · `quote` · `other`

### Règle importante — upload dans composants embarqués

`WithFileUploads` **ne fonctionne pas** dans un composant Livewire 4 embarqué. La solution adoptée est la délégation vers la page d'upload autonome via un lien `<a href>` avec les paramètres query string `attachable_type`, `attachable_id` et `back`.

---

## 3. Dashboard module

**Fichier :** `app/Livewire/Tenant/Dashboard.php`  
**Vue :** `resources/views/livewire/tenant/dashboard.blade.php`  
**Route :** `tenant.dashboard` (`/app`)

### Fonctionnement

- Composant Livewire full-page, `wire:poll.60s` (auto-refresh)
- **Sélecteur de période** : 7j / 30j / 3M (réactif)
- Toutes les données sont lues via `DB::connection('tenant')` — **aucun Eloquent** pour éviter le chargement des modèles de tous les packages

### Panneaux affichés selon les permissions

| Permission requise | Panneau affiché |
|--------------------|-----------------|
| `facturation.view` | KPI Finance (facturé, encaissé, reste dû, retards) |
| `devis.view` | Pipeline devis (funnel + taux conversion + valeur signée) |
| `clients.view` | Clients (total, actifs, nouveaux sur période) |
| `projets.view` | Projets (statuts, budget vs coût réel, projets en retard) |
| `maintenance.view` | Ordres maintenance (statuts, SLA dépassés, ordres critiques) |
| `achats.view` | Achats (KPI par statut, total commandé sur période) |

### Règle CSS — Livewire sans `@stack`

Le layout `layouts.app` n'a pas de `@stack('styles')`. Toujours placer le bloc `<style>` **à l'intérieur** du div racine du composant, pas dans `@push('styles')`.

```blade
{{-- ✅ Correct --}}
<div class="page-body">
    <style> ... </style>
    ...
</div>

{{-- ❌ Incorrect — styles ignorés silencieusement --}}
<div class="page-body">...</div>
@push('styles')
<style> ... </style>
@endpush
```

### Règle Livewire 4 — un seul élément racine

Un composant Livewire 4 **doit avoir exactement un élément racine**. Un `<style>` ou `<script>` sibling brise le binding `wire:` sur toute la page.

---

## 4. Reporting module

**Fichier :** `app/Livewire/Tenant/Reports.php`  
**Vue :** `resources/views/livewire/tenant/reports.blade.php`  
**Route :** `tenant.reports` (`/app/reports`)  
**Sidebar :** icône `chart`, visible pour tous les utilisateurs connectés

### Trois onglets

#### Vieillissement AR (AR Aging)

- Lecture des factures `status IN ('sent', 'overdue')` avec `amount_due > 0`
- Buckets par client : **0-30j / 31-60j / 61-90j / >90j** au-delà de la date d'échéance
- Barre de résumé, tableau détaillé par client, badge % risque
- Export CSV (BOM UTF-8, séparateur `;` pour Excel)

#### Revenus

- Sélecteur : 6 / 12 / 24 derniers mois
- KPI : total facturé, total encaissé, taux d'encaissement, nombre de factures
- Graphique barres groupées CSS-only (indigo = facturé, vert = encaissé)
- Tableau mensuel avec mini-barre de taux
- Requête PostgreSQL avec `TO_CHAR(issue_date, 'YYYY-MM')`
- Export CSV

#### Pipeline devis

- Sélecteur d'année (année courante - 3 ans)
- KPI : total, acceptés, taux conversion, valeur signée, pipeline en attente
- Entonnoir horizontal (Brouillons → Envoyés → Acceptés → Refusés)
- Tableau mensuel des devis acceptés
- Export CSV

### Export CSV

```php
return response()->streamDownload(function () use ($rows) {
    $out = fopen('php://output', 'w');
    fputs($out, "\xEF\xBB\xBF"); // BOM UTF-8 pour Excel
    fputcsv($out, ['Col1', 'Col2'], ';');
    foreach ($rows as $r) { fputcsv($out, [...], ';'); }
    fclose($out);
}, 'nom-fichier.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
```

---

## 5. Offer Kanban, planificateur préventif & notifications

### 5.1 Offer Kanban

**Fichier :** `packages/inovcom/offres/src/Http/Livewire/OffersKanban.php`  
**Vue :** `packages/inovcom/offres/resources/views/livewire/offers/kanban.blade.php`  
**Route :** `tenant.offres.kanban` (`/app/offres/kanban`)  
**Accès :** bouton "Kanban" dans la barre d'outils de la liste des offres

**Colonnes :** Brouillon → Envoyée → Acceptée → Refusée → Archivée

**Drag-and-drop :** HTML5 natif + Alpine.js

```js
// dragStart enregistre l'id, drop appelle le serveur
@this.moveOffer(id, status)
```

Côté serveur, `moveOffer()` utilise le `WorkflowStateMachine` si la transition est valide, sinon force le statut. Déclenche `OfferAccepted` si le statut devient `accepted`.

### 5.2 Planificateur préventif

**Commande :** `maintenance:generate-preventive`  
**Fichier :** `app/Console/Commands/GeneratePreventiveOrders.php`  
**Planification :** `dailyAt('06:00')`

**Logique :**
1. Récupère tous les contrats `status=active` avec `type IN ('preventive', 'full_service')`
2. Vérifie qu'aucun ordre préventif n'a été créé pour ce contrat depuis le début de la période (mois / trimestre / année selon `billing_cycle`)
3. Crée un `MaintenanceOrder` codé automatiquement avec `type=preventive`, `priority=normal`, `due_at` calculé depuis `resolution_time`

```bash
# Test manuel pour un tenant spécifique
php artisan maintenance:generate-preventive --tenant=kreobat
```

### 5.3 Vérificateur SLA

**Commande :** `maintenance:check-sla`  
**Fichier :** `app/Console/Commands/CheckSlaBreach.php`  
**Planification :** `hourly()`

Trouve les ordres ouverts/assignés/en cours dont le `due_at` est dans les 2 prochaines heures et envoie une notification `SlaBreachWarning` au technicien assigné.

```bash
php artisan maintenance:check-sla --tenant=kreobat
```

### 5.4 Notifications

Toutes les notifications utilisent le canal `database` (table `notifications` en base tenant). Elles s'affichent dans le composant `NotificationBell` existant (poll 60s).

| Classe | Déclencheur | Destinataire |
|--------|-------------|--------------|
| `App\Notifications\MaintenanceOrderAssigned` | `OrderForm::assignOrder()` | Technicien assigné |
| `App\Notifications\SlaBreachWarning` | `maintenance:check-sla` (horaire) | Technicien assigné |
| `App\Notifications\OfferAccepted` | `OffersKanban::moveOffer()` → `accepted` | Responsable assigné à l'offre |

**Structure du payload `toDatabase()` :**

```php
return [
    'type'    => 'offer_accepted',       // identifiant machine
    'title'   => 'Offre acceptée',       // titre affiché en gras
    'message' => 'L\'offre OFF00001…',   // corps
    'url'     => route('tenant.offres.edit', [...]), // lien cliquable
];
```

Le `NotificationBell` lit `$data['url']` (et `$data['link']` en fallback) pour le lien.

---

## 6. Login tenant redesign

**Fichier :** `resources/views/tenant/auth/login.blade.php`

Layout deux colonnes full-height :

| Colonne gauche (52%) | Colonne droite |
|----------------------|----------------|
| Fond sombre `#0f172a` + grille CSS + glow radial | Blanc, formulaire |
| Wordmark Inov-Com ERP | Champ email avec icône |
| Nom du tenant en gradient indigo→emerald | Champ mot de passe avec icône |
| Message de bienvenue (depuis `tenant_settings`) | Checkbox "Se souvenir de moi" |
| 3 KPI pills (ERP · 100% Sécurisé · Multi Modules) | Bouton gradient avec effet hover |
| Illustration SVG dashboard inline | "Connexion sécurisée" + "Propulsé par Inov-Com ERP" |
| Footer : dot vert + statut système | |

Tout en CSS inline dans le fichier — aucune dépendance externe, aucune image.

---

## 7. Règles techniques établies

### Installation de nouveaux packages

```bash
# ✅ Obligatoire — crée le symlink vendor/ + déclenche package:discover
composer require inovcom/monpackage:@dev --no-interaction

# ❌ Insuffisant — l'autoload est rechargé mais le ServiceProvider n'est pas découvert
composer dump-autoload
```

### Seeder de permissions (safe à relancer)

```bash
php artisan tenant:seed kreobat --class=TenantPermissionSeeder
```

Utilise `firstOrCreate` — ne duplique pas les permissions existantes.

### Migrations tenant

Les migrations des packages ne peuvent pas être publiées via `vendor:publish` (le `LazyModuleBoot` bloque `boot()` hors contexte tenant). Copier directement :

```
packages/inovcom/monpackage/database/migrations/
    → database/migrations/tenant/
```

Puis :

```bash
php artisan tenant:migrate kreobat
```

### Nommage des routes

| Pattern | Exemple |
|---------|---------|
| `tenant.{module}.index` | `tenant.maintenance.orders.index` |
| `tenant.{module}.create` | `tenant.projets.create` |
| `tenant.{module}.edit` | `tenant.offres.edit` |
| `tenant.{module}.kanban` | `tenant.offres.kanban` |

### Connexion DB dans les composants dashboard/reporting

Utiliser `DB::connection('tenant')` avec des clones de query builder :

```php
$q = DB::connection('tenant')->table('invoices');
$count = (clone $q)->where('status', 'draft')->count();
$total = (clone $q)->sum('total_ttc');
```

Ne pas utiliser les modèles Eloquent dans les composants transversaux (Dashboard, Reports) pour éviter de charger tous les ServiceProviders des packages.

---

## 8. Commandes artisan utiles

| Commande | Fréquence | Description |
|----------|-----------|-------------|
| `invoices:check-overdue` | Quotidien 07:00 | Marque les factures en retard, incrémente `reminder_count` |
| `maintenance:generate-preventive` | Quotidien 06:00 | Génère les ordres préventifs depuis les contrats actifs |
| `maintenance:check-sla` | Horaire | Alerte SLA imminente (≤ 2h) → notification technicien |
| `modules:sync` | Manuel | Synchronise `config/modules.php` → table `modules` |
| `tenant:migrate {code}` | Manuel | Applique les migrations en base tenant |
| `tenant:seed {code} --class=` | Manuel | Lance un seeder en base tenant |

---

## Récapitulatif des fichiers créés / modifiés en Phase 2

### Nouveaux packages

```
packages/inovcom/maintenance/     ← nouveau
packages/inovcom/dms/             ← nouveau
```

### Nouveaux composants Livewire

```
app/Livewire/Tenant/Dashboard.php
app/Livewire/Tenant/Reports.php
packages/inovcom/offres/src/Http/Livewire/OffersKanban.php
```

### Nouvelles commandes

```
app/Console/Commands/GeneratePreventiveOrders.php
app/Console/Commands/CheckSlaBreach.php
```

### Nouvelles notifications

```
app/Notifications/OfferAccepted.php
app/Notifications/MaintenanceOrderAssigned.php
app/Notifications/SlaBreachWarning.php
```

### Fichiers modifiés (core)

```
routes/web.php                            ← routes Dashboard, Reports
app/Console/Kernel.php                    ← 2 nouvelles tâches planifiées
composer.json                             ← dms, maintenance packages
config/modules.php                        ← entrées dms, maintenance
resources/views/layouts/app.blade.php     ← lien Rapports dans sidebar
resources/views/components/sidebar-icon.blade.php  ← icône chart
resources/views/tenant/auth/login.blade.php        ← redesign complet
resources/views/livewire/tenant/notification-bell.blade.php ← url + title
resources/css/app.css                     ← classes DMS, module-tabs
database/seeders/TenantPermissionSeeder.php        ← 47 permissions
```
