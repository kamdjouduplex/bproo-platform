# Phase 1 — Core Business Logic Guide

## What was built in Phase 1

Phase 1 transforms the BPROO ERP from basic CRUD screens into a real business application with financial intelligence, automated workflows, and professional PDF output.

---

## 1. Financial calculations on Quotes and Invoices

### Quote form (`/app/devis/create` or `/app/devis/{id}/edit`)

Every quote now supports:

| Field | Where | Purpose |
|---|---|---|
| **Remise globale (%)** | Financial panel | Global percentage discount on the whole quote |
| **TVA (%)** | Financial panel | VAT rate applied after discount |
| **Coût de revient** | Each line | Internal cost — used to compute margin |
| **Type de ligne** | Each line | Service / Produit / Travaux / Sous-total |

**Live totals panel** (recalculates on every keystroke):

```
Total HT        = Σ(qty × unit_price) for all lines
Remise          = Total HT × discount_percent / 100
Net HT          = Total HT − Remise
TVA             = Net HT × tax_rate / 100
Total TTC       = Net HT + TVA
Marge %         = (Total HT − Σ costs) / Total HT × 100
```

The margin indicator turns **red below 15%** and **green at or above 30%**.

### Invoice form (`/app/facturation/create` or `/app/facturation/{id}/edit`)

Same financial logic as quotes, plus:

- **Délai de paiement (jours)** — auto-computes the due date when issue date changes
- **Solde dû** — tracks remaining amount after partial payments
- Locked fields when invoice is `paid` or `cancelled` — prevents accidental edits

---

## 2. Workflow state machines

Every document follows a strict state machine. Transitions are enforced server-side by `WorkflowStateMachine::transitionTo()`.

### Quote transitions

```
draft ──→ sent ──→ accepted  (★ triggers project creation)
            └──→ refused ──→ draft
```

### Invoice transitions

```
draft ──→ sent ──→ paid        (terminal)
            ├──→ overdue ──→ paid
            └──→ cancelled      (terminal)
```

### Project transitions

```
planned ──→ in_progress ──→ completed ──→ closed
               └──→ on_hold ──┘
```

### Offer transitions

```
draft ──→ submitted ──→ accepted ──→ archived
               └──→ refused
```

### How to trigger transitions

In the UI: use the **action buttons** in the top action bar (Envoyer, Accepter, Refuser, etc.).

In code:

```php
$quote->transitionTo('accepted', auth('tenant')->id());
```

If the transition is not allowed, an `InvalidWorkflowTransitionException` is thrown with a descriptive message.

---

## 3. The flagship automation: Quote accepted → Project created

When a quote is **accepted** (via the "Accepter" button or via `transitionTo('accepted', ...)`):

1. `WorkflowTransitioned` event is fired
2. `HandleWorkflowTransitioned` listener dispatches `QuoteAccepted` event
3. `CreateProjectFromQuote` listener (queued) creates a project:
   - Code: `PRJ00001`, `PRJ00002`, ...
   - Title: same as quote title
   - Client: from quote
   - Budget: `quote.total_ttc`
   - Status: `planned`
4. `ProjectCreatedFromQuote` event is fired (ready for email/Slack notifications in Phase 2)

The listener is **idempotent** — running it twice on the same quote will not create a duplicate project.

---

## 4. Invoice payment recording

On an invoice in `sent` or `overdue` status, click **"Enregistrer un paiement"** in the action bar.

Fill in:
- **Montant** (defaults to the remaining amount due)
- **Date** de paiement
- **Moyen de paiement**: Virement / Chèque / Espèces / Mobile Money / Carte / Autre
- **Référence**: cheque number, transfer reference, etc.

The system:
- Creates an `invoice_payments` record
- Updates `amount_paid` and `amount_due` on the invoice
- **Auto-transitions** the invoice to `paid` if `amount_due` reaches 0

All past payments are displayed in the "Historique des paiements" table on the form.

---

## 5. Dunning — overdue invoice detection

A scheduled command runs **every morning at 07:00**:

```bash
php artisan invoices:check-overdue
```

For each active tenant, it:
1. Finds all `sent` invoices with `due_date < today`
2. Transitions them to `overdue` via the state machine
3. Increments `reminder_count` (dunning escalation counter)
4. Updates `last_reminder_at`
5. Fires `InvoiceOverdue` event (email listeners ready in Phase 2)

To run manually for a single tenant:

```bash
php artisan invoices:check-overdue --tenant=TENANT_CODE
```

---

## 6. PDF generation

### Requirements

PDF requires `barryvdh/laravel-dompdf`. Run once:

```bash
composer require barryvdh/laravel-dompdf
```

### Generating PDFs

| Document | URL | Route name |
|---|---|---|
| Quote | `/app/devis/{id}/pdf` | `tenant.devis.pdf` |
| Invoice | `/app/facturation/{id}/pdf` | `tenant.facturation.pdf` |

Both routes stream the PDF inline (opens in browser). The "PDF" button on each form redirects there.

### Customizing PDFs

Edit the templates in `resources/views/pdf/`:

| File | Purpose |
|---|---|
| `layout.blade.php` | Shared page frame, CSS, header, footer structure |
| `quote.blade.php` | Quote PDF content |
| `invoice.blade.php` | Invoice PDF content |

To add your company address on PDFs, add to `config/app.php` or create `config/pdf.php`:

```php
// config/pdf.php
return [
    'company_address' => "123 Rue du Commerce\nAbidjan, Côte d'Ivoire\nTél: +225 XX XX XX XX",
];
```

---

## 7. Project form enhancements

The project form now includes:

| Field | Purpose |
|---|---|
| **Type de projet** | Construction / Maintenance / Prestation de service / Autre |
| **Priorité** | Basse / Normale / Haute / Urgente |
| **Budget** | Auto-filled from accepted quote's `total_ttc` |
| **N° de contrat / marché** | External reference (public procurement) |
| **Adresse du chantier** | Site location |

Progress and actual cost are computed automatically from tasks (when Phase 2 task management UI is added). The KPI bar at the top shows Budget, Coût réel, and Avancement %.

---

## 8. Authorization

Every Livewire component now enforces permissions via `AuthorizesWithTenant`:

```php
// In mount():
$this->tenantAuthorize('devis.view');

// In save():
$this->tenantAuthorize('devis.create');  // or 'devis.edit'
```

Permissions map to the 48-permission RBAC matrix defined in `UsersModule`. If the current user doesn't have the required permission, a `403 Unauthorized` response is returned.

---

## 9. Running the Phase 1 migrations

```bash
php artisan tenants:migrate
```

This runs all new tenant migrations across all provisioned tenants:

| Migration | Creates / Alters |
|---|---|
| `2026_04_05_000001` | Enhances `quotes` and `quote_lines` tables |
| `2026_04_05_000002` | Enhances `invoices` table |
| `2026_04_05_000003` | Creates `invoice_payments` table |
| `2026_04_05_000004` | Enhances `projects` table |
| `2026_04_05_000005` | Creates `project_phases` table |
| `2026_04_05_000006` | Creates `project_tasks` table |
| `2026_04_05_000007` | Creates `project_members` table |
| `2026_04_05_000008` | Enhances `clients` table |
| `2026_04_05_000009` | Creates `client_contacts` table |
| `2026_04_05_000010` | Creates `client_activities` table |

---

## 10. What's coming in Phase 2

Phase 2 will add:

- **Project tasks UI** — create/assign tasks linked to phases, track time
- **Client 360° view** — contacts, activities, documents, quotes/invoices in one screen
- **Email notifications** — send quotes and invoices by email, dunning emails
- **Purchase Orders UI** — full `achats` module screens
- **Reporting dashboards** — revenue, margins, project costs per period
- **Document attachments** — file uploads on quotes, invoices, projects
