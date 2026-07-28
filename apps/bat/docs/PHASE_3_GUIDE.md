# Phase 3 — Guide de référence

> **Période :** Semaines 7 à 12  
> **Statut :** ✅ Complété  
> **Cible :** BPROO ERP — plateforme multi-tenant Laravel 10 / Livewire 4

---

## Sommaire

1. [Planning module](#1-planning-module)
2. [Suivi terrain module](#2-suivi-terrain-module) _(à venir)_
3. [Audit trail](#3-audit-trail) _(à venir)_
4. [Règles techniques établies en Phase 3](#4-règles-techniques-établies-en-phase-3)
5. [Commandes artisan utiles](#5-commandes-artisan-utiles)

---

## 1. Planning module

**Package :** `packages/inovcom/planning`  
**Clé module :** `planning`  
**Permissions :** `planning.view`, `planning.create`, `planning.edit`, `planning.delete`

### Modèle

| Modèle | Table | Description |
|--------|-------|-------------|
| `Appointment` | `planning_appointments` | Rendez-vous, visites terrain, réunions, jalons projet |

### Champs du modèle `Appointment`

| Champ | Type | Description |
|-------|------|-------------|
| `code` | `string` | Auto-généré : `APT00001`, `APT00002`, … |
| `title` | `string` | Titre du rendez-vous |
| `type` | `string` | `visit_terrain` · `reunion` · `maintenance` · `project_milestone` · `other` |
| `status` | `string` | `scheduled` · `confirmed` · `done` · `cancelled` |
| `start_at` | `datetime` | Début |
| `end_at` | `datetime` | Fin |
| `location` | `string` | Lieu (optionnel) |
| `notes` | `text` | Notes internes (optionnel) |
| `assigned_to` | `FK` | Utilisateur responsable (optionnel) |
| `client_id` | `FK` | Client lié (optionnel) |
| `project_id` | `FK` | Projet lié (optionnel) |
| `maintenance_order_id` | `FK` | Ordre de maintenance lié (optionnel) |

### Helpers du modèle

```php
$apt->typeBadgeClass()    // → 'apt-type--visit', 'apt-type--reunion', …
$apt->typeLabel()         // → 'Visite terrain', 'Réunion', …
$apt->statusBadgeClass()  // → 'badge-status--scheduled', …
$apt->durationMinutes()   // → durée en minutes
```

### Scopes Eloquent

```php
Appointment::on('tenant')->upcoming()               // à partir de maintenant, non annulés, triés
Appointment::on('tenant')->forMonth($year, $month)  // tous les RDV d'un mois donné
```

### Composants Livewire

| Composant | Route | Description |
|-----------|-------|-------------|
| `PlanningCalendar` | `tenant.planning.index` | Calendrier mensuel + vue liste |
| `AppointmentForm` | `tenant.planning.create` / `tenant.planning.edit` | CRUD rendez-vous |

### Fonctionnalités du calendrier (`PlanningCalendar`)

- **Vue mois** : grille 7×N (lundi en tête), pills colorées par type (max 3 par cellule + « +N autres »)
- **Vue liste** : 30 prochains jours, groupés par date, avec heure début/fin, lieu, responsable, client
- **Navigation** : mois précédent / suivant / Aujourd'hui
- **Filtres réactifs** : par type de rendez-vous, par utilisateur assigné
- **Suppression inline** avec `wire:confirm`
- Lien direct vers création : bouton « Nouveau » conditionné à `planning.create`

### Formulaire (`AppointmentForm`)

- Style système : `card` + `form-grid` + `field` / `field-label` + `input` — **aucun CSS custom**
- Génère le code `APT00001` automatiquement à la création
- Sélecteurs déroulants : type, statut, responsable, client, projet, ordre de maintenance
- `start_at` pré-rempli à l'heure suivante arrondie, `end_at` à `start_at + 1h`
- `assigned_to` pré-rempli avec l'utilisateur connecté

### Génération automatique du code

```php
$max = Appointment::on('tenant')
    ->where('code', 'like', 'APT%')
    ->pluck('code')
    ->map(fn(string $c) => (int) substr($c, 3))
    ->filter(fn(int $n) => $n > 0)
    ->max();
$code = 'APT' . str_pad((string)(($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
```

### Migration

```
database/migrations/tenant/2026_04_05_100001_create_planning_appointments_table.php
```

Index créés : `(start_at, end_at)`, `assigned_to`, `client_id`, `status`.

### Installation

```bash
# 1. Le package est déjà requis via composer.json (@dev path)
composer require inovcom/planning:@dev --no-interaction

# 2. Synchroniser la table modules
php artisan modules:sync

# 3. Migrer la base tenant
php artisan tenant:migrate kreobat

# 4. Seeder permissions
php artisan tenant:seed kreobat --class=TenantPermissionSeeder
```

---

## 2. Suivi terrain module

> ⏳ **À implémenter — Semaines 9–10**

**Package prévu :** `packages/inovcom/suivi`  
**Clé module :** `suivi`

### Périmètre prévu

| Fonctionnalité | Description |
|----------------|-------------|
| Rapport chantier | Rapport journalier lié à un projet : météo, effectifs, avancement, incidents |
| Photos terrain | Upload via le pattern DMS `EntityDocuments` (lien vers `DocumentUpload`) |
| PV de réception | Procès-verbal client signé, génération PDF (DomPDF) |
| Statut terrain | Indicateur d'avancement par projet (% réalisé) |

---

## 3. Audit trail

**Statut :** ✅ Complété  
**Route :** `tenant.audit` (`/app/audit`)  
**Composant :** `app/Livewire/Tenant/AuditLog.php`  
**Vue :** `resources/views/livewire/tenant/audit-log.blade.php`  
**Permission :** `audit.view`  
**Sidebar :** icône `shield`, visible uniquement pour les utilisateurs ayant `audit.view`

### Architecture

Le système d'audit est entièrement piloté par le trait `Auditable` du kernel — aucun package séparé. Il est composé de trois éléments :

| Élément | Fichier | Rôle |
|---------|---------|------|
| `Auditable` (trait) | `packages/inovcom/kernel/src/Traits/Auditable.php` | Hook Eloquent `created`/`updated`/`deleted` → écrit dans `audit_logs` |
| `AuditLog` (model) | `packages/inovcom/kernel/src/Models/AuditLog.php` | Lecture/écriture table `audit_logs`, connexion `tenant` |
| Migration | `database/migrations/tenant/2026_04_04_000001_create_audit_logs_table.php` | Table `audit_logs` en base tenant |

### Table `audit_logs`

| Colonne | Type | Description |
|---------|------|-------------|
| `auditable_type` | `string(100)` | Nom de la table source (ex. `quotes`, `projects`) |
| `auditable_id` | `bigint` | ID de l'enregistrement modifié |
| `event` | `string(100)` | `created` · `updated` · `deleted` · `status_changed` |
| `user_id` | `bigint nullable` | Utilisateur auteur (`null` = Système) |
| `old_values` | `jsonb nullable` | Valeurs avant modification |
| `new_values` | `jsonb nullable` | Valeurs après modification |
| `ip_address` | `string(45)` | IP de la requête |
| `user_agent` | `string(255)` | User-agent tronqué |
| `created_at` | `timestamp` | Horodatage (pas de `updated_at`) |

### Modèles auditables (trait Auditable actif)

| Modèle | Table |
|--------|-------|
| `Client` | `clients` |
| `Offer` | `offers` |
| `Quote` | `quotes` |
| `Project` | `projects` |
| `Invoice` | `invoices` |
| `PurchaseOrder` | `purchase_orders` |
| `MaintenanceContract` | `maintenance_contracts` |
| `MaintenanceOrder` | `maintenance_orders` |
| `Intervention` | `interventions` |
| `Document` | `documents` |

### Composant `AuditLog` (viewer)

**Filtres réactifs :**
- Entité (liste dynamique des `auditable_type` présents dans la table)
- Utilisateur
- Événement (`created` / `updated` / `deleted` / `status_changed`)
- Plage de dates (défaut : 30 derniers jours)

**Affichage des modifications :**
- `created` → "N champ(s) initialisé(s)"
- `deleted` → "N champ(s) supprimé(s)"
- `updated` → champ par champ : valeur avant en rouge barré ~~old~~ → valeur après en vert

**Note :** les champs `password`, `remember_token`, `updated_at` sont exclus de l'audit par `$auditExclude` dans le trait.

### Ajouter l'audit à un nouveau modèle

```php
use InovCom\Kernel\Traits\Auditable;

class SiteReport extends TenantModel
{
    use Auditable;
    // … le reste du modèle
}
```

C'est tout — le trait `bootAuditable()` s'accroche automatiquement aux événements Eloquent.

---

## 4. Règles techniques établies en Phase 3

### Tenant code dans les vues

Le tenant est transmis en query string (`?tenant=kreobat`), **pas** comme segment de route. Toujours récupérer ainsi :

```php
@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp
```

**Ne jamais utiliser** `request()->route('tenant')` dans les vues des packages — retourne `null` en contexte tenant.

### Style formulaire — aucun CSS custom

Les formulaires de Phase 3 utilisent exclusivement les classes système déjà définies dans `resources/css/app.css` :

```blade
<section class="card">
    <h2 class="card-title">Titre</h2>
    <div class="form-grid" style="grid-template-columns:1fr 1fr;">
        <div class="field">
            <label class="field-label">Label <span class="text-error">*</span></label>
            <input type="text" class="input" wire:model="champ">
            @error('champ') <span class="field-error">{{ $message }}</span> @enderror
        </div>
    </div>
</section>
<div class="page-actions" style="margin-top:16px;">
    <a href="..." class="btn btn-ghost">Annuler</a>
    <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
</div>
```

| Classe | Rôle |
|--------|------|
| `page-body` | Wrapper racine du composant Livewire (unique élément racine) |
| `card` | Section de formulaire (fond blanc, bordure) |
| `card-title` | Titre de section `<h2>` |
| `form-grid` | Grille CSS pour les champs (1 colonne par défaut, surchargeable via `style`) |
| `field` | Conteneur d'un champ |
| `field-label` | Label du champ |
| `field-error` | Message d'erreur de validation |
| `input` | Input / select / textarea |
| `input-error` | Bordure rouge sur un input invalide |
| `page-actions` | Barre d'actions en bas de page |
| `btn btn-primary` | Bouton principal |
| `btn btn-ghost` | Bouton secondaire / annulation |

### Requête DB cross-module dans les composants

Pour lire des données d'un autre module (ex. projets depuis le planning) sans charger les modèles Eloquent :

```php
// ✅ Correct — query builder direct
$projects = DB::connection('tenant')->table('projects')
    ->whereNotIn('status', ['closed', 'cancelled'])
    ->orderBy('title')   // ← vérifier le nom réel de la colonne (title, name, code…)
    ->get(['id', 'title', 'code']);

// ❌ À éviter — charge tous les ServiceProviders du package
$projects = \InovCom\Projets\Models\Project::on('tenant')->get();
```

**Toujours vérifier le nom de la colonne dans la migration avant d'écrire la requête.** Erreur classique : `projects` utilise `title`, pas `name`.

### Liaisons polymorphiques optionnelles

Le modèle `Appointment` peut être lié à un client, un projet ET un ordre de maintenance simultanément — ces trois FK sont indépendantes et toutes optionnelles. Stocker `null` si non renseigné (ne pas stocker `0`).

```php
'client_id'            => $this->client_id ?: null,
'project_id'           => $this->project_id ?: null,
'maintenance_order_id' => $this->maintenance_order_id ?: null,
```

---

## 5. Commandes artisan utiles

| Commande | Fréquence | Description |
|----------|-----------|-------------|
| `modules:sync` | Manuel | Synchronise `config/modules.php` → table `modules` |
| `tenant:migrate {code}` | Manuel | Applique les migrations en base tenant |
| `tenant:seed {code} --class=TenantPermissionSeeder` | Manuel | Seede les permissions (safe à relancer) |

---

## Récapitulatif des fichiers créés / modifiés en Phase 3

### Nouveau package

```
packages/inovcom/planning/
├── composer.json
├── database/migrations/
│   └── 2026_04_05_100001_create_planning_appointments_table.php
├── resources/views/livewire/
│   ├── calendar.blade.php
│   └── appointment-form.blade.php
└── src/
    ├── Http/Livewire/
    │   ├── PlanningCalendar.php
    │   └── AppointmentForm.php
    ├── Models/
    │   └── Appointment.php
    ├── PlanningModule.php
    └── PlanningServiceProvider.php
```

### Nouveau package Suivi terrain

```
packages/inovcom/suivi/
├── composer.json
├── database/migrations/
│   └── 2026_04_05_200001_create_site_reports_table.php
├── resources/views/livewire/reports/
│   ├── index.blade.php
│   └── form.blade.php
└── src/
    ├── Http/Livewire/
    │   ├── SiteReportsIndex.php
    │   └── SiteReportForm.php
    ├── Models/
    │   └── SiteReport.php
    ├── SuiviModule.php
    └── SuiviServiceProvider.php
```

### Audit trail (composant viewer uniquement)

```
app/Livewire/Tenant/AuditLog.php
resources/views/livewire/tenant/audit-log.blade.php
```

_(La table `audit_logs` et le trait `Auditable` existaient déjà dans le kernel.)_

### Fichiers modifiés (core)

```
composer.json                               ← repository + require + autoload inovcom/planning + inovcom/suivi
config/modules.php                          ← entrée suivi (menu_order 53) + planning (52)
database/migrations/tenant/                 ← copies des migrations planning + suivi
database/seeders/TenantPermissionSeeder.php ← +1 audit.view, +5 suivi.*, +4 planning.*
routes/web.php                              ← route tenant.audit
resources/views/layouts/app.blade.php       ← lien Audit dans sidebar (@can audit.view)
resources/views/components/sidebar-icon.blade.php ← icônes clipboard + shield
```
