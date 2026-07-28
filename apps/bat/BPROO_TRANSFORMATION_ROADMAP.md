# BPROO ERP — Transformation Roadmap
## From Foundational CRUD to Enterprise-Grade ERP

> **This document is the single source of truth for the evolution of BPROO ERP.**
> Every implementation decision must be validated against it.
> It is a living document — update it as the system evolves.

---

## Table of Contents

1. [Vision & North Star](#1-vision--north-star)
2. [Current State Audit](#2-current-state-audit)
3. [Core Architectural Upgrades](#3-core-architectural-upgrades)
4. [Module Evolution Plan](#4-module-evolution-plan)
5. [New Modules](#5-new-modules)
6. [Integration Map — How Modules Connect](#6-integration-map--how-modules-connect)
7. [Implementation Phases](#7-implementation-phases)
8. [Database Schema Evolution](#8-database-schema-evolution)
9. [RBAC Matrix](#9-rbac-matrix)
10. [Technical Standards](#10-technical-standards)

---

## 1. Vision & North Star

### What BPROO Must Become

BPROO is not a form manager. It is the **operational brain** of a construction, maintenance, and services company. Every action in one part of the system must have **intelligent consequences** in other parts.

The system must embody three principles:

**1. Connected Intelligence**
When a quote is accepted, a project is born automatically. When a project closes, an invoice drafts itself. When an invoice goes overdue, a reminder is sent. No human should have to manually push data from one module to another.

**2. Visibility Without Effort**
Every manager must see — in real time — the financial health, project status, and team workload without running a single query or opening a spreadsheet.

**3. Enforcement Without Friction**
A technician cannot close a project that has unpaid invoices. An accountant cannot send an invoice that has no project. A salesperson cannot create a PO without a valid quote. Rules are enforced by the system, not by discipline.

### Business Domain
Targeted at SMEs in:
- Building construction & renovation
- Electrical, HVAC, plumbing, painting, furniture services
- Building maintenance & SLA-based service contracts

### Core Business Cycle (The Spine of the System)

```
LEAD/OPPORTUNITY
      ↓
  CLIENT INTAKE
      ↓
  OFFER RECEIVED  ←── (Site visit, documents collected)
      ↓
  QUOTE GENERATED ←── (Items, labor, margin, taxes)
      ↓ [accepted]
  PROJECT CREATED ←── (Auto-created from accepted quote)
      ↓
  PLANNING        ←── (Phases, tasks, resources assigned)
      ↓
  PROCUREMENT     ←── (POs linked to project)
      ↓
  EXECUTION       ←── (Field ops, technician reporting)
      ↓
  PROGRESS INVOICE←── (Advance or milestone invoices)
      ↓
  PROJECT CLOSED  ←── (Final invoice, signature)
      ↓
  PAYMENT TRACKED ←── (AR aging, dunning)
```

**Parallel track for Maintenance:**
```
SLA CONTRACT
      ↓
  MAINTENANCE REQUEST
      ↓
  MAINTENANCE ORDER ←── (Auto-assign by SLA priority)
      ↓
  INTERVENTION    ←── (Technician dispatched, mobile app)
      ↓
  CLOSURE REPORT  ←── (Digital signature, photo upload)
      ↓
  INVOICE         ←── (Per-intervention or periodic)
```

---

## 2. Current State Audit

### What Exists (and Works)
| Component | Quality | Notes |
|-----------|---------|-------|
| Multi-tenancy (DB-per-tenant) | Solid | Foundation is correct |
| Module system (lazy boot, lifecycle) | Solid | Well-designed |
| Async job provisioning | Solid | 3 retries, logging |
| Basic CRUD: Clients, Offers, Quotes, Projects, Invoices, PO | MVP | Forms work, no business logic |
| Module marketplace scaffold | Skeleton | Remote API placeholder |
| Module-level RBAC (enable/disable per tenant) | Working | Admin portal only |
| RBAC permission matrix UI | Working | Not enforced at action level |
| i18n (FR/EN) | Partial | lang files exist, not complete |

### What is Critically Missing
| Gap | Impact | Priority |
|-----|--------|----------|
| No workflow enforcement (free-form status changes) | High — data integrity | P0 |
| No PDF generation (quotes/invoices) | High — unusable for business | P0 |
| No email notifications | High — invisible to clients | P0 |
| No permission enforcement at action level | High — security risk | P0 |
| No payment tracking (invoice "paid" is just a flag) | High — financials wrong | P0 |
| No project tasks/phases/resources | High — projects are hollow | P1 |
| No audit trail | Medium — compliance | P1 |
| No inter-module automation (quote accepted → project) | High — still manual | P1 |
| No goods receipt for POs | Medium — cycle incomplete | P1 |
| No real-time notifications | Medium — poor UX | P2 |
| No maintenance module | High — core domain missing | P1 |
| No document management | Medium — operations need it | P2 |
| No dashboards/KPIs | Medium — blind management | P2 |
| No API layer | Medium — no mobile/integration | P3 |
| No inventory tracking | Low — items are catalog only | P3 |
| No AI integration | Low — future feature | P4 |

---

## 3. Core Architectural Upgrades

These are **cross-cutting** improvements that must be in place **before** new features are built. They are the infrastructure that makes the system intelligent.

---

### 3.1 Event Bus & Automation Engine

**Current state:** 5 events defined (ItemCreated, ClientCreated, etc.), zero listeners.

**Target:** A full event-driven automation layer where module actions trigger consequences across the system.

**Architecture:**

```
Action (Livewire component)
    → fires Domain Event
        → Event Listener (async, queued)
            → Side effects: create records, send notifications, log audit
```

**Events to implement (by priority):**

```php
// Offers
OfferStatusChanged(Offer $offer, string $from, string $to, int $userId)

// Quotes
QuoteStatusChanged(Quote $quote, string $from, string $to, int $userId)
QuoteAccepted(Quote $quote)   // ← triggers ProjectCreated
QuoteSent(Quote $quote)       // ← triggers email to client

// Projects
ProjectCreated(Project $project, Quote $quote)
ProjectStatusChanged(Project $project, string $from, string $to)
ProjectCompleted(Project $project)   // ← triggers final invoice draft

// Invoices
InvoiceStatusChanged(Invoice $invoice, string $from, string $to)
InvoiceSent(Invoice $invoice)        // ← triggers client email
InvoicePaid(Invoice $invoice, Payment $payment)  // ← triggers receipt
InvoiceOverdue(Invoice $invoice)     // ← triggers dunning

// Maintenance
MaintenanceOrderCreated(MaintenanceOrder $order)
InterventionCompleted(Intervention $intervention)  // ← triggers invoice

// Procurement
PurchaseOrderValidated(PurchaseOrder $po, int $validatedBy)
GoodsReceived(PurchaseOrder $po, GoodsReceipt $receipt)
```

**Listeners to implement:**

| Event | Listener | Action |
|-------|----------|--------|
| QuoteAccepted | CreateProjectFromQuote | Auto-create Project with same client |
| QuoteAccepted | NotifyProjectManager | Email/notification to assigned PM |
| QuoteSent | SendQuoteToClient | Email with PDF attachment |
| ProjectCompleted | DraftFinalInvoice | Create invoice draft from project |
| InvoiceSent | SendInvoiceToClient | Email with PDF attachment |
| InvoiceOverdue | TriggerDunningSequence | Queue reminder emails day 1/7/14 |
| InvoicePaid | UpdateARLedger | Mark invoice, create receipt record |
| InterventionCompleted | TriggerInterventionInvoice | Draft maintenance invoice |
| GoodsReceived | UpdateProjectPOStatus | Update PO tracking on project |

**Implementation:**
- All listeners implement `ShouldQueue` for async processing
- Each listener catches its own exceptions and logs failures
- Failed listeners → `failed_jobs` table with retry capability

---

### 3.2 Workflow Engine (State Machine)

**Current state:** Status fields are free-form dropdowns. Any value can be set at any time.

**Target:** Enforced state machines with transition rules, guards, and callbacks.

**Implementation pattern:**

```php
// packages/inovcom/kernel/src/Contracts/HasWorkflow.php
interface HasWorkflow
{
    public function allowedTransitions(): array;
    public function canTransitionTo(string $status): bool;
    public function transitionTo(string $status, int $userId, ?string $reason = null): void;
}

// packages/inovcom/kernel/src/Traits/WorkflowStateMachine.php
trait WorkflowStateMachine
{
    public function canTransitionTo(string $status): bool
    {
        $transitions = $this->allowedTransitions();
        return in_array($status, $transitions[$this->status] ?? []);
    }

    public function transitionTo(string $status, int $userId, ?string $reason = null): void
    {
        if (!$this->canTransitionTo($status)) {
            throw new InvalidWorkflowTransitionException(
                "Cannot transition {$this->getTable()} from '{$this->status}' to '{$status}'"
            );
        }

        $from = $this->status;
        $this->status = $status;
        $this->save();

        // Auto-dispatch event
        event(new StatusChanged($this, $from, $status, $userId, $reason));

        // Auto-log audit trail
        AuditLog::record($this, 'status_changed', $userId, [
            'from' => $from,
            'to' => $status,
            'reason' => $reason,
        ]);
    }
}
```

**State transition maps per entity:**

```
Offer:    draft → submitted → [accepted | refused] → archived
                     ↑______________|  (can reopen refused offers)

Quote:    draft → sent → [accepted | refused]
                              ↓ (accepted only)
                         invoice_generated (final state)

Project:  planned → in_progress → [on_hold → in_progress] → completed → closed
                                       ↑___________________|

Invoice:  draft → sent → [paid | overdue → sent (reminder)] → [cancelled]

PO:       draft → pending_validation → validated → ordered → [partially_received | received] → cancelled
```

---

### 3.3 Audit Trail System

**Current state:** No change history. ModuleEvent table only tracks module install/uninstall.

**Target:** Full audit trail on all business entities.

**New table: `audit_logs`** (in tenant database)

```sql
CREATE TABLE audit_logs (
    id          BIGSERIAL PRIMARY KEY,
    auditable_type VARCHAR(255) NOT NULL,  -- 'quote', 'invoice', etc.
    auditable_id   BIGINT NOT NULL,
    event          VARCHAR(100) NOT NULL,   -- 'created', 'updated', 'status_changed', 'deleted'
    user_id        BIGINT REFERENCES users(id),
    old_values     JSONB,
    new_values     JSONB,
    ip_address     VARCHAR(45),
    user_agent     TEXT,
    created_at     TIMESTAMP NOT NULL
);

CREATE INDEX idx_audit_auditable ON audit_logs(auditable_type, auditable_id);
CREATE INDEX idx_audit_user ON audit_logs(user_id);
CREATE INDEX idx_audit_created ON audit_logs(created_at);
```

**Kernel trait: `Auditable`**
```php
trait Auditable
{
    public static function bootAuditable(): void
    {
        static::created(fn($m) => AuditLog::record($m, 'created', auth()->id()));
        static::updated(fn($m) => AuditLog::record($m, 'updated', auth()->id(), [
            'old' => $m->getOriginal(),
            'new' => $m->getDirty(),
        ]));
        static::deleted(fn($m) => AuditLog::record($m, 'deleted', auth()->id()));
    }
}
```

All business models (Client, Quote, Project, Invoice, PO, etc.) will use `Auditable`.

---

### 3.4 PDF & Document Generation

**Current state:** No PDF library. No templates. Quotes and invoices cannot be sent to clients.

**Target:** Professional PDF output for all client-facing documents.

**Library:** `barryvdh/laravel-dompdf`

**Documents to generate:**
- Quote PDF (header, client info, line items, totals, terms, signature block)
- Invoice PDF (same structure, with payment details and bank info)
- Delivery note / Goods Receipt
- Purchase Order
- Intervention Report (with signature image)
- Project Progress Report

**Architecture:**

```php
// packages/inovcom/kernel/src/Services/PdfGenerator.php
class PdfGenerator
{
    public function quote(Quote $quote): StreamedResponse
    public function invoice(Invoice $invoice): StreamedResponse
    public function purchaseOrder(PurchaseOrder $po): StreamedResponse
    public function interventionReport(Intervention $intervention): StreamedResponse
}
```

**PDF templates** (Blade views):
```
resources/views/pdf/
    ├── quote.blade.php
    ├── invoice.blade.php
    ├── purchase-order.blade.php
    ├── intervention-report.blade.php
    └── partials/
        ├── header.blade.php      (logo, tenant name, address)
        ├── client-block.blade.php
        ├── line-items-table.blade.php
        └── footer.blade.php      (terms, bank details, signature)
```

**Integration:**
- Download button on Quote/Invoice form (Livewire action)
- Auto-generate and attach to email when document is "sent"
- Store generated PDF in DMS (linked to entity)

---

### 3.5 Notification & Email System

**Current state:** `mckenziearts/laravel-notify` for toast messages. No email infrastructure.

**Target:** Full notification system: database notifications + email.

**Notification channels:**
1. **In-app** (database channel — bell icon in header)
2. **Email** (Mailable + Markdown templates)
3. **SMS** (future — pluggable)

**Notifications to implement:**

```php
// Sent to clients (external emails)
QuoteSentToClientNotification      // "Your quote #DEV-00001 is ready"
InvoiceSentToClientNotification    // "Invoice #FAC-00001 — EUR 1,500.00 due 2026-05-15"
InvoiceReminderNotification        // Day 1/7/14 after due date

// Sent to internal users (in-app + email)
QuoteAcceptedNotification          // → Sales team, Project Manager
ProjectAssignedNotification        // → Assigned user
PurchaseOrderPendingValidationNotification // → Finance/Director
MaintenanceOrderAssignedNotification // → Technician
```

**Database `notifications` table** (in tenant DB):
```sql
CREATE TABLE notifications (
    id          UUID PRIMARY KEY,
    type        VARCHAR(255) NOT NULL,
    notifiable_type VARCHAR(255) NOT NULL,
    notifiable_id   BIGINT NOT NULL,
    data        JSONB NOT NULL,
    read_at     TIMESTAMP,
    created_at  TIMESTAMP,
    updated_at  TIMESTAMP
);
```

**Header bell icon (Livewire component):**
- Shows unread count
- Dropdown with recent 10 notifications
- Mark all read / per-notification read
- Polls every 30 seconds (or wire:poll)

---

### 3.6 Permission Enforcement

**Current state:** Permissions stored in DB, never checked at action level.

**Target:** Permissions enforced at every layer: middleware, component, view.

**Default roles and permissions to seed on tenant provisioning:**

| Permission Key | Admin | Director | Project Manager | Sales | Accountant | Technician |
|----------------|-------|----------|-----------------|-------|------------|-----------|
| clients.view | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| clients.create | ✓ | ✓ | — | ✓ | — | — |
| clients.edit | ✓ | ✓ | — | ✓ | — | — |
| clients.delete | ✓ | ✓ | — | — | — | — |
| quotes.view | ✓ | ✓ | ✓ | ✓ | ✓ | — |
| quotes.create | ✓ | ✓ | — | ✓ | — | — |
| quotes.send | ✓ | ✓ | — | ✓ | — | — |
| projects.view | ✓ | ✓ | ✓ | — | ✓ | ✓ |
| projects.manage | ✓ | ✓ | ✓ | — | — | — |
| invoices.view | ✓ | ✓ | — | — | ✓ | — |
| invoices.create | ✓ | ✓ | — | — | ✓ | — |
| invoices.send | ✓ | ✓ | — | — | ✓ | — |
| purchases.validate | ✓ | ✓ | — | — | ✓ | — |
| maintenance.view | ✓ | ✓ | ✓ | — | — | ✓ |
| maintenance.dispatch | ✓ | ✓ | ✓ | — | — | — |
| reports.view | ✓ | ✓ | ✓ | — | ✓ | — |
| admin.users | ✓ | ✓ | — | — | — | — |

**Enforcement pattern (Livewire components):**
```php
// In mount() of every component
public function mount(): void
{
    $this->authorize('clients.view'); // throws AuthorizationException if missing
}

// In action methods
public function save(): void
{
    $this->authorize($this->record->exists ? 'clients.edit' : 'clients.create');
    // ... proceed
}
```

**Gate registration (kernel AppServiceProvider):**
```php
Gate::define('clients.view', fn(User $user) => $user->hasPermission('clients.view'));
// ... one per permission key, or a catch-all:
Gate::before(fn(User $user, string $ability) => $user->hasPermission($ability) ?: null);
```

**Blade directives for field-level visibility:**
```blade
@can('invoices.approve')
    <button wire:click="approve">Approve</button>
@endcan
```

---

## 4. Module Evolution Plan

Each existing module is enhanced from simple CRUD to a real business domain component.

---

### 4.1 Clients Module → CRM

**Current:** name, email, phone, address, credit_limit, metadata blob

**New fields to add:**
```sql
ALTER TABLE clients ADD COLUMN segment VARCHAR(50);           -- 'vip', 'regular', 'prospect'
ALTER TABLE clients ADD COLUMN assigned_to BIGINT REFERENCES users(id);
ALTER TABLE clients ADD COLUMN payment_terms INT DEFAULT 30;  -- days
ALTER TABLE clients ADD COLUMN currency VARCHAR(3) DEFAULT 'EUR';
ALTER TABLE clients ADD COLUMN website VARCHAR(255);
ALTER TABLE clients ADD COLUMN notes TEXT;
ALTER TABLE clients ADD COLUMN last_contact_at TIMESTAMP;
```

**New related tables:**

```sql
-- Contact persons for a client (e.g., billing contact, site contact)
CREATE TABLE client_contacts (
    id          BIGSERIAL PRIMARY KEY,
    client_id   BIGINT NOT NULL REFERENCES clients(id) ON DELETE CASCADE,
    name        VARCHAR(255) NOT NULL,
    role        VARCHAR(100),         -- 'billing', 'technical', 'decision_maker'
    email       VARCHAR(255),
    phone       VARCHAR(50),
    is_primary  BOOLEAN DEFAULT false,
    timestamps
);

-- Activity / interaction log
CREATE TABLE client_activities (
    id          BIGSERIAL PRIMARY KEY,
    client_id   BIGINT NOT NULL REFERENCES clients(id),
    user_id     BIGINT REFERENCES users(id),
    type        VARCHAR(50),          -- 'call', 'meeting', 'email', 'site_visit', 'note'
    subject     VARCHAR(255),
    notes       TEXT,
    activity_at TIMESTAMP NOT NULL,
    timestamps
);
```

**New features:**
- Client 360° view: all offers, quotes, projects, invoices, purchase orders in one tab
- Client financial summary: total billed, total paid, outstanding balance, credit usage
- Activity timeline with logging of calls, meetings, emails
- Client contacts management
- Credit limit enforcement (block invoice if client over limit)
- Smart search: by code, name, city, segment, assigned user

---

### 4.2 Offers Module → Lead Pipeline

**Current:** title, category, status, source, assigned_to

**New fields:**
```sql
ALTER TABLE offers ADD COLUMN budget_estimate DECIMAL(15,2);
ALTER TABLE offers ADD COLUMN expected_close_date DATE;
ALTER TABLE offers ADD COLUMN probability INT DEFAULT 50;       -- % chance of winning
ALTER TABLE offers ADD COLUMN site_visit_date TIMESTAMP;
ALTER TABLE offers ADD COLUMN site_visit_done BOOLEAN DEFAULT false;
ALTER TABLE offers ADD COLUMN site_address TEXT;
ALTER TABLE offers ADD COLUMN follow_up_at TIMESTAMP;
ALTER TABLE offers ADD COLUMN lost_reason TEXT;                 -- filled when refused
```

**New features:**
- Kanban view (pipeline board): columns = statuses, drag cards between columns
- Conversion rate analytics per source/category/period
- Follow-up reminder: scheduled notification when follow_up_at arrives
- Site visit checklist: mark as done, attach documents
- Offer → Quote conversion: one click creates a quote pre-filled from offer data
- Loss analysis: required reason when refusing an offer

---

### 4.3 Quotes Module → Smart Quotation

**Current:** basic line items, total_ht, no tax, no discounts, no PDF

**New fields:**
```sql
ALTER TABLE quotes ADD COLUMN discount_percent DECIMAL(5,2) DEFAULT 0;
ALTER TABLE quotes ADD COLUMN discount_amount DECIMAL(15,2) DEFAULT 0;
ALTER TABLE quotes ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT 20.00;    -- TVA
ALTER TABLE quotes ADD COLUMN tax_amount DECIMAL(15,2) DEFAULT 0;
ALTER TABLE quotes ADD COLUMN total_ttc DECIMAL(15,2) DEFAULT 0;
ALTER TABLE quotes ADD COLUMN currency VARCHAR(3) DEFAULT 'EUR';
ALTER TABLE quotes ADD COLUMN terms TEXT;                              -- payment terms text
ALTER TABLE quotes ADD COLUMN internal_notes TEXT;                     -- not on PDF
ALTER TABLE quotes ADD COLUMN margin_percent DECIMAL(5,2);             -- calculated
ALTER TABLE quotes ADD COLUMN sent_at TIMESTAMP;
ALTER TABLE quotes ADD COLUMN accepted_at TIMESTAMP;
ALTER TABLE quotes ADD COLUMN refused_at TIMESTAMP;
ALTER TABLE quotes ADD COLUMN version INT DEFAULT 1;                   -- revision tracking
ALTER TABLE quotes ADD COLUMN parent_id BIGINT REFERENCES quotes(id); -- for revisions
```

**Quote lines enhancement:**
```sql
ALTER TABLE quote_lines ADD COLUMN item_id BIGINT REFERENCES items(id) ON DELETE SET NULL;
ALTER TABLE quote_lines ADD COLUMN cost DECIMAL(15,2) DEFAULT 0;      -- for margin calc
ALTER TABLE quote_lines ADD COLUMN discount_percent DECIMAL(5,2) DEFAULT 0;
ALTER TABLE quote_lines ADD COLUMN line_type VARCHAR(50) DEFAULT 'product'; -- product | labor | expense
ALTER TABLE quote_lines ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT 20.00;
```

**New features:**
- **Auto-fill from Items catalog:** select item → auto-fill description, unit price, cost
- **Live margin calculator:** shows margin % as lines are filled
- **Tax calculation:** TVA applied per line or globally (configurable per tenant)
- **Discount support:** line-level % discount and global discount
- **PDF generation + email delivery:** "Send to Client" button generates PDF, emails client
- **Quote versioning:** "Revise" creates a new version (parent_id link)
- **Quote comparison:** side-by-side view of quote versions
- **Expiry enforcement:** auto-status change to "expired" after valid_until date (scheduled job)
- **Acceptance flow:** client link (email) → confirmation page → auto-status to accepted
- **One-click project creation:** from accepted quote

**Auto-calculations (all in real-time via Livewire):**
```
line.amount     = line.quantity × line.unit_price × (1 - line.discount_percent/100)
quote.total_ht  = Σ line.amount
quote.discount  = quote.total_ht × discount_percent/100
quote.net_ht    = quote.total_ht - quote.discount
quote.tax       = Σ (line.amount × line.tax_rate/100)
quote.total_ttc = quote.net_ht + quote.tax
quote.margin    = (Σ line.amount - Σ line.cost) / Σ line.amount × 100
```

---

### 4.4 Projects Module → Project Management

**Current:** static record with code, title, status, start/end dates, one assigned user

**This is the most underdeveloped core module. It needs the largest enhancement.**

**New fields:**
```sql
ALTER TABLE projects ADD COLUMN budget DECIMAL(15,2);          -- from quote total
ALTER TABLE projects ADD COLUMN actual_cost DECIMAL(15,2) DEFAULT 0;  -- sum of POs
ALTER TABLE projects ADD COLUMN progress_percent INT DEFAULT 0;
ALTER TABLE projects ADD COLUMN priority VARCHAR(20) DEFAULT 'normal'; -- low | normal | high | urgent
ALTER TABLE projects ADD COLUMN project_type VARCHAR(50);       -- 'construction' | 'maintenance' | 'renovation'
ALTER TABLE projects ADD COLUMN contract_number VARCHAR(100);
ALTER TABLE projects ADD COLUMN site_address TEXT;
ALTER TABLE projects ADD COLUMN completed_at TIMESTAMP;
ALTER TABLE projects ADD COLUMN closed_at TIMESTAMP;
```

**New related tables:**

```sql
-- Project phases / milestones
CREATE TABLE project_phases (
    id              BIGSERIAL PRIMARY KEY,
    project_id      BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    title           VARCHAR(255) NOT NULL,
    description     TEXT,
    position        INT NOT NULL DEFAULT 0,
    status          VARCHAR(50) DEFAULT 'pending',     -- pending | in_progress | completed
    planned_start   DATE,
    planned_end     DATE,
    actual_start    DATE,
    actual_end      DATE,
    budget          DECIMAL(15,2),
    progress_percent INT DEFAULT 0,
    timestamps
);

-- Tasks within phases
CREATE TABLE project_tasks (
    id              BIGSERIAL PRIMARY KEY,
    project_id      BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    phase_id        BIGINT REFERENCES project_phases(id) ON DELETE SET NULL,
    assigned_to     BIGINT REFERENCES users(id) ON DELETE SET NULL,
    title           VARCHAR(255) NOT NULL,
    description     TEXT,
    status          VARCHAR(50) DEFAULT 'todo',        -- todo | in_progress | done | blocked
    priority        VARCHAR(20) DEFAULT 'normal',
    planned_start   DATE,
    planned_end     DATE,
    actual_start    DATE,
    actual_end      DATE,
    estimated_hours DECIMAL(8,2),
    actual_hours    DECIMAL(8,2),
    position        INT DEFAULT 0,
    timestamps
);

-- Project team (multiple users on one project)
CREATE TABLE project_members (
    id          BIGSERIAL PRIMARY KEY,
    project_id  BIGINT NOT NULL REFERENCES projects(id) ON DELETE CASCADE,
    user_id     BIGINT NOT NULL REFERENCES users(id) ON DELETE CASCADE,
    role        VARCHAR(100),                    -- 'project_manager', 'technician', 'foreman'
    timestamps,
    UNIQUE (project_id, user_id)
);

-- Project financial summary view (computed)
-- invoiced_amount  = SUM of invoices linked to project
-- paid_amount      = SUM of paid invoices
-- committed_cost   = SUM of validated POs
-- actual_cost      = SUM of received POs
```

**New features:**
- **Phase-based planning:** break project into phases, each with tasks
- **Task board (Kanban):** per-project task board by status column
- **Team management:** add multiple users with roles (PM, foreman, technician)
- **Budget tracking panel:** quote value vs committed POs vs actual spend
- **Timeline view:** simple Gantt (phase bars on a date grid)
- **Progress auto-calculation:** project progress = weighted average of phase progress
- **Purchase orders panel:** all POs linked to this project with totals
- **Invoices panel:** all invoices linked to this project
- **Documents panel:** all files attached to this project (via DMS)
- **Activity log:** who did what and when (Auditable trait + custom timeline)

---

### 4.5 Invoicing Module → Billing & AR

**Current:** draft invoice with lines, status, no payment records

**New fields:**
```sql
ALTER TABLE invoices ADD COLUMN currency VARCHAR(3) DEFAULT 'EUR';
ALTER TABLE invoices ADD COLUMN discount_percent DECIMAL(5,2) DEFAULT 0;
ALTER TABLE invoices ADD COLUMN tax_rate DECIMAL(5,2) DEFAULT 20.00;
ALTER TABLE invoices ADD COLUMN payment_terms INT DEFAULT 30;      -- days from issue
ALTER TABLE invoices ADD COLUMN payment_method VARCHAR(50);        -- 'bank_transfer' | 'check' | 'cash'
ALTER TABLE invoices ADD COLUMN bank_details TEXT;                 -- for PDF
ALTER TABLE invoices ADD COLUMN sent_at TIMESTAMP;
ALTER TABLE invoices ADD COLUMN paid_at TIMESTAMP;
ALTER TABLE invoices ADD COLUMN overdue_at TIMESTAMP;              -- calculated: issue_date + payment_terms
ALTER TABLE invoices ADD COLUMN invoice_type VARCHAR(50) DEFAULT 'standard';  -- standard | advance | credit_note
ALTER TABLE invoices ADD COLUMN credit_note_for BIGINT REFERENCES invoices(id);
ALTER TABLE invoices ADD COLUMN reminder_count INT DEFAULT 0;
ALTER TABLE invoices ADD COLUMN last_reminder_at TIMESTAMP;
ALTER TABLE invoices ADD COLUMN internal_notes TEXT;
```

**New related tables:**

```sql
-- Payment receipts (multiple partial payments allowed)
CREATE TABLE invoice_payments (
    id              BIGSERIAL PRIMARY KEY,
    invoice_id      BIGINT NOT NULL REFERENCES invoices(id) ON DELETE CASCADE,
    amount          DECIMAL(15,2) NOT NULL,
    payment_date    DATE NOT NULL,
    payment_method  VARCHAR(50),                   -- 'bank_transfer' | 'check' | 'cash'
    reference       VARCHAR(255),                   -- bank reference / check number
    notes           TEXT,
    recorded_by     BIGINT REFERENCES users(id),
    timestamps
);
```

**New features:**
- **Payment recording:** partial payments supported, invoice auto-marked "paid" when fully covered
- **AR aging report:** overdue invoices grouped by 0-30, 31-60, 61-90, 90+ days
- **Dunning automation:** scheduled job checks overdue invoices, queues reminder notifications
  - Day 1 after due: first reminder email
  - Day 7: second reminder (firmer tone)
  - Day 14: escalation notification to Director
- **Advance invoices:** create invoice for deposit % before project starts
- **Credit notes:** link to original invoice, negative amount
- **PDF generation:** professional invoice PDF with tenant logo, payment details
- **Email delivery:** send invoice + PDF directly to client email
- **Invoice numbering:** auto-sequence per year (FAC-2026-0001)
- **Outstanding balance:** real-time client balance = Σ unpaid invoices

---

### 4.6 Procurement Module → Purchase-to-Pay

**Current:** PO with validation workflow, no goods receipt, no invoice matching

**New related tables:**

```sql
-- Goods receipt (what was actually delivered)
CREATE TABLE goods_receipts (
    id                  BIGSERIAL PRIMARY KEY,
    purchase_order_id   BIGINT NOT NULL REFERENCES purchase_orders(id),
    received_by         BIGINT REFERENCES users(id),
    received_at         TIMESTAMP NOT NULL,
    notes               TEXT,
    is_partial          BOOLEAN DEFAULT false,
    timestamps
);

CREATE TABLE goods_receipt_lines (
    id                  BIGSERIAL PRIMARY KEY,
    goods_receipt_id    BIGINT NOT NULL REFERENCES goods_receipts(id) ON DELETE CASCADE,
    purchase_order_line_id BIGINT REFERENCES purchase_order_lines(id),
    description         VARCHAR(255),
    quantity_ordered    DECIMAL(10,3),
    quantity_received   DECIMAL(10,3) NOT NULL,
    quantity_rejected   DECIMAL(10,3) DEFAULT 0,
    rejection_reason    TEXT,
    timestamps
);

-- Supplier catalog (what each supplier sells and at what price)
CREATE TABLE supplier_items (
    id              BIGSERIAL PRIMARY KEY,
    supplier_id     BIGINT NOT NULL REFERENCES suppliers(id) ON DELETE CASCADE,
    item_id         BIGINT REFERENCES items(id) ON DELETE SET NULL,
    supplier_sku    VARCHAR(100),
    description     VARCHAR(255),
    unit_price      DECIMAL(15,2),
    currency        VARCHAR(3) DEFAULT 'EUR',
    lead_time_days  INT,
    min_order_qty   DECIMAL(10,3) DEFAULT 1,
    is_active       BOOLEAN DEFAULT true,
    timestamps
);
```

**New features:**
- **Goods receipt:** record what was actually received per PO line (partial deliveries supported)
- **Rejection tracking:** record rejected quantities with reason
- **Supplier catalog:** link items to suppliers with prices and lead times
- **Best price comparison:** when creating PO, show cheapest supplier for each item
- **3-way match validation:** block supplier invoice payment if no GR matching the PO
- **PO to project cost:** received POs automatically update project actual_cost
- **Purchase requisition:** users request items, manager approves → converts to PO
- **Supplier performance:** on-time delivery rate, rejection rate per supplier

---

### 4.7 Items Module → Product & Service Catalog

**Current:** basic catalog with SKU, price tiers, category, brand, unit

**Enhancements:**
- Complete the `item_id` relationships (QuoteLine, InvoiceLine, PurchaseOrderLine all reference items but no Eloquent relationship defined)
- Add supplier mapping via `supplier_items` table
- Add item images via DMS attachment
- Add item-level tax classification
- Service items: items with `type = 'service'` have no stock tracking
- Stock tracking optional: if `Items` module + `Inventory` sub-feature enabled, track `quantity_on_hand`

---

## 5. New Modules

---

### 5.1 Maintenance Module (`packages/inovcom/maintenance`)

This is a core domain for BPROO's target market. Clients have ongoing maintenance contracts, and technicians are dispatched to service their facilities.

**Entities:**

```sql
-- SLA / Maintenance contracts
CREATE TABLE maintenance_contracts (
    id              BIGSERIAL PRIMARY KEY,
    code            VARCHAR(50) UNIQUE NOT NULL,
    client_id       BIGINT NOT NULL REFERENCES clients(id),
    title           VARCHAR(255),
    type            VARCHAR(50),                  -- 'preventive' | 'corrective' | 'full_service'
    status          VARCHAR(50) DEFAULT 'active', -- active | suspended | expired
    start_date      DATE NOT NULL,
    end_date        DATE,
    price_per_month DECIMAL(15,2),
    response_time   INT,                          -- hours (SLA)
    resolution_time INT,                          -- hours (SLA)
    billing_cycle   VARCHAR(20) DEFAULT 'monthly', -- monthly | quarterly | yearly
    sites           JSONB,                         -- list of covered sites/addresses
    terms           TEXT,
    timestamps
);

-- Maintenance requests (incoming from client or planned)
CREATE TABLE maintenance_orders (
    id              BIGSERIAL PRIMARY KEY,
    code            VARCHAR(50) UNIQUE NOT NULL,
    contract_id     BIGINT REFERENCES maintenance_contracts(id) ON DELETE SET NULL,
    client_id       BIGINT NOT NULL REFERENCES clients(id),
    title           VARCHAR(255) NOT NULL,
    description     TEXT,
    type            VARCHAR(50),                  -- 'corrective' | 'preventive' | 'emergency'
    priority        VARCHAR(20) DEFAULT 'normal', -- low | normal | high | critical
    status          VARCHAR(50) DEFAULT 'open',   -- open | assigned | in_progress | done | closed | cancelled
    site_address    TEXT,
    reported_by     VARCHAR(255),                  -- client contact name
    reported_at     TIMESTAMP NOT NULL,
    due_at          TIMESTAMP,                     -- SLA deadline
    assigned_to     BIGINT REFERENCES users(id),
    closed_at       TIMESTAMP,
    timestamps
);

-- Each physical visit / intervention
CREATE TABLE interventions (
    id                  BIGSERIAL PRIMARY KEY,
    maintenance_order_id BIGINT NOT NULL REFERENCES maintenance_orders(id) ON DELETE CASCADE,
    technician_id       BIGINT NOT NULL REFERENCES users(id),
    status              VARCHAR(50) DEFAULT 'scheduled',  -- scheduled | in_progress | done
    scheduled_at        TIMESTAMP NOT NULL,
    started_at          TIMESTAMP,
    completed_at        TIMESTAMP,
    duration_minutes    INT,
    work_done           TEXT,
    findings            TEXT,
    next_action         TEXT,
    client_signature    TEXT,                             -- base64 signature image
    signed_at           TIMESTAMP,
    photos              JSONB,                            -- array of DMS file IDs
    materials_used      JSONB,                            -- [{item_id, qty, unit_price}]
    timestamps
);
```

**Features:**
- **Recurring scheduling:** preventive maintenance auto-generates orders on schedule (daily job)
- **SLA countdown:** live timer showing time remaining vs SLA deadline
- **Priority-based dispatch:** urgent orders alert via notification immediately
- **Technician calendar:** view assigned interventions by day/week
- **Mobile-friendly intervention form:** simplified UI for field use
- **Digital signature capture:** client signs intervention report on tablet/mobile
- **Photo upload:** technician attaches photos to intervention
- **Materials tracking:** record items consumed during intervention (links to Items module)
- **Auto-invoice from intervention:** completed intervention → draft invoice (or append to periodic invoice)
- **SLA breach alerts:** notification to manager when intervention exceeds SLA

---

### 5.2 Document Management System (`packages/inovcom/dms`)

Every business entity needs attached files — contracts, photos, plans, permits, reports.

**Tables:**

```sql
CREATE TABLE documents (
    id              BIGSERIAL PRIMARY KEY,
    tenant_code     VARCHAR(50) NOT NULL,
    category        VARCHAR(100),                  -- 'contract' | 'plan' | 'permit' | 'photo' | 'report'
    title           VARCHAR(255) NOT NULL,
    description     TEXT,
    filename        VARCHAR(255) NOT NULL,
    mime_type       VARCHAR(100),
    file_size       BIGINT,                         -- bytes
    storage_path    VARCHAR(500) NOT NULL,          -- S3 key or local path
    version         INT DEFAULT 1,
    parent_id       BIGINT REFERENCES documents(id) ON DELETE SET NULL,  -- for versioning
    uploaded_by     BIGINT REFERENCES users(id),
    timestamps
);

-- Polymorphic attachment (link documents to any entity)
CREATE TABLE document_attachments (
    id              BIGSERIAL PRIMARY KEY,
    document_id     BIGINT NOT NULL REFERENCES documents(id) ON DELETE CASCADE,
    attachable_type VARCHAR(100) NOT NULL,          -- 'offer', 'quote', 'project', 'maintenance_order'
    attachable_id   BIGINT NOT NULL,
    timestamps
);

CREATE INDEX idx_doc_attachments ON document_attachments(attachable_type, attachable_id);
```

**Storage backends (configurable per tenant):**
- Local disk (`storage/tenants/{code}/documents/`)
- S3-compatible (AWS, MinIO, Scaleway Object Storage)

**Features:**
- Upload from any entity form (drag & drop)
- Document preview (images, PDFs)
- Version history (upload new version, old retained)
- Category-based organization
- Full-text search across document titles
- Download / share links (temporary signed URLs for S3)
- Storage usage tracking per tenant
- Auto-attach generated PDFs (quotes, invoices) to their entities

---

### 5.3 Reporting Engine (`packages/inovcom/reporting`)

**Report types:**

| Report | Data Source | Format |
|--------|------------|--------|
| AR Aging | Invoices + Payments | Table + chart |
| Revenue by Period | Invoices (paid) | Bar chart + table |
| Margin by Project | Projects + Quotes + POs | Table |
| Offer Conversion Rate | Offers → Quotes → Projects | Funnel chart |
| Project Profitability | Quote value vs actual costs | Table + chart |
| Supplier Performance | POs + GR | Table |
| Technician Activity | Interventions | Calendar + table |
| Outstanding Payments | Invoices overdue | Sorted table |
| Monthly Billing Summary | Invoices by client | Table |

**Architecture:**
```php
// packages/inovcom/reporting/src/Reports/
class ArAgingReport implements ReportContract
class RevenueReport implements ReportContract
class ProjectProfitabilityReport implements ReportContract
// etc.

interface ReportContract {
    public function title(): string;
    public function filters(): array;
    public function build(array $filters): Collection;
    public function columns(): array;
    public function chartType(): ?string;
}
```

**Export formats:**
- PDF (via dompdf)
- Excel (via maatwebsite/excel)
- CSV (native Laravel)
- Scheduled email delivery (monthly report auto-sent to Director)

---

### 5.4 Dashboard Engine (`packages/inovcom/dashboard`)

**Role-based dashboards — each role sees relevant KPIs:**

**Director Dashboard:**
- Revenue MTD / YTD (vs same period last year)
- Open offers value (pipeline)
- Outstanding AR (overdue invoices)
- Active projects + % complete
- Gross margin % (quotes sent this month)
- New clients this month

**Project Manager Dashboard:**
- My active projects (status, progress, days remaining)
- Overdue tasks (tasks past planned_end)
- POs pending validation
- Team workload (tasks per person this week)

**Sales Dashboard:**
- My offers pipeline (by status)
- Quotes pending acceptance
- Quote win rate (%)
- Revenue from my clients this month

**Accountant Dashboard:**
- Invoices to send (draft invoices)
- Overdue invoices (value + count)
- Payments received this week
- POs pending validation (financial approval)
- Cash flow (invoiced vs received by week)

**Technician Dashboard:**
- Today's interventions
- Upcoming interventions (this week)
- Overdue tasks on projects
- My open maintenance orders

**KPI Widget architecture:**
```php
interface KpiWidget {
    public function label(): string;
    public function value(): mixed;
    public function format(): string;  // 'currency', 'percent', 'count', 'date'
    public function trend(): ?float;   // % change vs last period
    public function color(): string;   // 'green', 'red', 'blue', 'yellow'
    public function link(): ?string;   // click-through to detail
}
```

---

### 5.5 Field Operations (`packages/inovcom/field`)

A mobile-optimized interface for technicians in the field.

**Mobile-first design requirements:**
- Large touch targets (48px minimum)
- Offline-capable (cache intervention data, sync on reconnect)
- Camera integration (photo upload)
- Canvas-based signature capture
- GPS location stamping on intervention start/end

**Livewire components:**
- `FieldDashboard` — Today's schedule, current intervention
- `InterventionCheckIn` — Start intervention, stamp GPS + time
- `InterventionReport` — Record work done, materials, findings
- `SignatureCapture` — Full-screen canvas for client signature
- `PhotoUpload` — Multi-photo upload with camera capture

**Layout:** Separate `layouts/field.blade.php` — stripped down, no sidebar, mobile-first CSS

---

## 6. Integration Map — How Modules Connect

This section defines the **automated consequences** of every major action. This is what makes BPROO an ERP, not a CRUD app.

```
┌─────────────────────────────────────────────────────────────────┐
│                        MODULE INTEGRATIONS                       │
└─────────────────────────────────────────────────────────────────┘

OFFERS ──────────────────────────────────────────────────────────┐
  │  offer.accepted                                               │
  └──► Notify sales team (in-app + email)                        │
       Follow-up reminder created if no quote in X days          │
                                                                  │
QUOTES ───────────────────────────────────────────────────────────┤
  │  quote.sent                                                   │
  ├──► PDF generated + stored in DMS                             │
  ├──► Email sent to client with PDF attached                     │
  └──► In-app notification to sales user                         │
  │                                                               │
  │  quote.accepted                                               │
  ├──► PROJECT auto-created (same client, copy title, budget)    │
  ├──► Offer status updated to 'accepted' (if linked)            │
  ├──► Notify Project Manager (assigned_to)                      │
  └──► Optional: advance INVOICE created (if deposit % > 0)     │
                                                                  │
PROJECTS ─────────────────────────────────────────────────────────┤
  │  project.completed                                            │
  ├──► Final INVOICE drafted (from project lines)                │
  └──► Notify Accountant                                         │
  │                                                               │
  │  project.po_received (GoodsReceived event)                   │
  └──► actual_cost updated on project                            │
                                                                  │
INVOICES ──────────────────────────────────────────────────────────┤
  │  invoice.sent                                                 │
  ├──► PDF generated + stored in DMS                             │
  ├──► Email sent to client                                       │
  └──► Notify Accountant                                         │
  │                                                               │
  │  invoice.overdue (nightly job check)                         │
  ├──► Day 1: reminder notification + email to client            │
  ├──► Day 7: stronger reminder + notify Director                │
  └──► Day 14: escalation notification to Director               │
  │                                                               │
  │  invoice.paid (payment recorded)                             │
  ├──► Client balance updated                                     │
  └──► Revenue KPI widgets refreshed                             │
                                                                  │
PROCUREMENT ───────────────────────────────────────────────────────┤
  │  po.pending_validation                                        │
  └──► Notify Finance/Director for approval                      │
  │                                                               │
  │  po.validated                                                 │
  └──► Notify requester                                          │
  │                                                               │
  │  goods.received                                               │
  └──► Project actual_cost updated                               │
                                                                  │
MAINTENANCE ───────────────────────────────────────────────────────┤
  │  intervention.completed + signed                              │
  ├──► INVOICE drafted (or appended to monthly batch)            │
  ├──► DMS: intervention report PDF generated                    │
  └──► Notify Accountant / Client                                │
  │                                                               │
  │  maintenance_order.overdue_sla (hourly check)                │
  └──► ALERT to Manager with current SLA breach status           │
                                                                  │
ITEMS ──────────────────────────────────────────────────────────────┤
  │  Auto-fill in QuoteLine, InvoiceLine, POLine                 │
  └──► ItemsApi used: price tier, cost, tax rate                 │
                                                                  │
CLIENTS ────────────────────────────────────────────────────────────┘
  │  credit_limit enforcement
  └──► ClientsApi.canMakePurchase() blocks invoice creation
       if client exceeds credit limit (Director override available)
```

---

## 7. Implementation Phases

Work is organized in phases. **Each phase must be complete and stable before the next begins.**

---

### Phase 0 — Foundation (Sprint 1-2) — *NOW*

**Goal:** Infrastructure that every feature depends on. No visible user features, but critical.

**Tasks:**
- [x] Install `barryvdh/laravel-dompdf` and `maatwebsite/excel` (added to composer.json)
- [x] Create `Auditable` trait in kernel + `audit_logs` migration
- [x] Create `WorkflowStateMachine` trait in kernel + `WorkflowTransitioned` event
- [x] Seed default roles (Admin, Director, PM, Sales, Accountant, Technician) + full permission set
- [x] Register permission Gates in AuthServiceProvider (catch-all Gate::before)
- [x] Add `notifications` table to tenant migrations
- [x] Create `NotificationBell` Livewire component (header)
- [x] Implement event listeners for all 5 existing events + WorkflowTransitioned
- [x] Register `achats` module in `config/modules.php`
- [x] Fix all missing Eloquent relationships (QuoteLine.item, InvoiceLine.item, PurchaseOrderLine.item)
- [ ] Wire `authorize()` calls into ALL existing Livewire components → Phase 1 task
- [ ] Complete i18n: audit all Livewire blade files → ongoing
- [ ] Add `order` column to modules table migration (already exists in git status)

---

### Phase 1 — Core Business Logic (Sprint 3-6)

**Goal:** The existing modules become real business tools, not just forms.

**Tasks:**
- [ ] **State machines:** implement `WorkflowStateMachine` on Offer, Quote, Project, Invoice, PurchaseOrder
- [ ] **Quote enhancements:** tax, discounts, margin calc, currency, terms, version tracking
- [ ] **Quote PDF:** template + download button + "Send to Client" (email + DMS)
- [ ] **Invoice enhancements:** payment_terms, overdue_at, reminder_count, credit_note support
- [ ] **Invoice PDF:** template + download + "Send to Client"
- [ ] **Payment recording:** `invoice_payments` table + payment form on invoice
- [ ] **QuoteAccepted → auto-create Project** (listener + job)
- [ ] **InvoiceOverdue dunning** (nightly scheduled job)
- [ ] **Client 360° view:** tabs for offers, quotes, projects, invoices, activities
- [ ] **Client contacts:** `client_contacts` table + management UI
- [ ] **Project phases + tasks:** new tables + Kanban task board
- [ ] **Project team members:** `project_members` table + UI
- [ ] **Project financial panel:** budget vs committed vs actual
- [ ] **Goods Receipt:** new table + form linked to PO
- [ ] **Supplier items catalog:** `supplier_items` table + UI
- [ ] **AR Aging report:** basic version
- [ ] **Email notifications:** QuoteSent, InvoiceSent, QuoteAccepted (to PM)

---

### Phase 2 — New Modules (Sprint 7-10)

**Goal:** Complete the domain — maintenance, documents, dashboards.

**Tasks:**
- [ ] **Maintenance module:** contracts, orders, interventions, SLA tracking
- [ ] **Intervention form (mobile-first):** start/stop, work log, materials, photos
- [ ] **Digital signature:** canvas-based capture, stored as image in DMS
- [ ] **DMS module:** document table, polymorphic attachments, storage config
- [ ] **File upload UI:** drag-and-drop on all entity forms
- [ ] **Dashboard module:** role-based KPI panels per the spec above
- [ ] **Reporting module:** AR Aging, Revenue, Margin, Conversion reports + export
- [ ] **Offer Kanban view:** pipeline board with drag-and-drop
- [ ] **Preventive maintenance scheduler:** cron job auto-generates orders

---

### Phase 3 — Advanced Features (Sprint 11-14)

**Goal:** System-level intelligence and external connectivity.

**Tasks:**
- [ ] **REST API:** Laravel Sanctum-authenticated API for all core entities
- [ ] **API documentation:** Laravel Scribe or Swagger auto-gen
- [ ] **Real-time broadcasting:** Laravel Echo + Pusher/Soketi for notifications
- [ ] **Scheduled reports:** Director email with weekly PDF summary
- [ ] **Quote acceptance portal:** client-facing page (no login) to view + accept quote
- [ ] **Intervention self-service portal:** client views their maintenance history
- [ ] **Multi-currency support:** exchange rates, invoice in client currency
- [ ] **Audit trail UI:** per-entity timeline of all changes

---

### Phase 4 — AI & Platform (Sprint 15+)

**Goal:** Competitive differentiation and platform maturity.

**Tasks:**
- [ ] **Smart Quote Generator:** natural language → quote lines (Claude API integration)
- [ ] **AI Project Summary:** generate project status report from tasks/logs
- [ ] **Predictive insights:** flag projects likely to overrun budget/timeline
- [ ] **Module marketplace:** activate `marketplace.inovcom.com` API + install remote packages
- [ ] **Docker + CI/CD:** containerize, GitHub Actions pipeline, staging → production
- [ ] **Tenant onboarding wizard:** guided setup for new tenants (logo, currency, users, initial data)
- [ ] **White-label support:** per-tenant custom domain, custom email from address

---

## 8. Database Schema Evolution

### New tables by phase

**Phase 0:**
- `audit_logs` (tenant DB)
- `notifications` (tenant DB)
- `client_activities` (tenant DB — early addition for 360° view)

**Phase 1:**
- `client_contacts`
- `project_phases`
- `project_tasks`
- `project_members`
- `invoice_payments`
- `goods_receipts`
- `goods_receipt_lines`
- `supplier_items`

**Phase 2:**
- `maintenance_contracts`
- `maintenance_orders`
- `interventions`
- `documents`
- `document_attachments`

**Phase 3:**
- `api_tokens` (Laravel Sanctum — system DB)
- `exchange_rates` (system DB)

### Migration strategy
- All tenant-DB table additions go in `database/migrations/tenant/` with date prefix
- All new module tables go in the module package's `database/migrations/` with the module's migration tag
- Never modify a column without a dedicated migration (no breaking changes)
- Add indexes explicitly: all foreign keys + frequently filtered columns

---

## 9. RBAC Matrix

### Roles to seed on tenant provisioning

```php
// Seeded in TenantProvisioner
$roles = [
    'admin'           => 'Administrateur',
    'director'        => 'Directeur',
    'project_manager' => 'Chef de Projet',
    'sales'           => 'Commercial',
    'accountant'      => 'Comptable',
    'technician'      => 'Technicien',
];
```

### Permission keys (full list)

```
clients.*            (view, create, edit, delete, export)
offers.*             (view, create, edit, delete, accept, refuse)
quotes.*             (view, create, edit, delete, send, accept, refuse, export)
projects.*           (view, create, edit, delete, manage_team, manage_phases)
invoices.*           (view, create, edit, delete, send, record_payment, cancel)
purchases.*          (view, create, edit, delete, validate, receive)
maintenance.*        (view, create, edit, delete, dispatch, close)
items.*              (view, create, edit, delete)
reports.*            (view, export, schedule)
documents.*          (view, upload, delete)
users.*              (view, create, edit, delete, assign_roles)
settings.*           (view, edit)
```

---

## 10. Technical Standards

### Code Standards (non-negotiable)

1. **No raw SQL in Livewire components** — all queries go through Eloquent models or dedicated service classes
2. **No business logic in views** — blade templates are for display only
3. **Every status change goes through `transitionTo()`** — never `$model->status = 'X'; $model->save()`
4. **Every domain event is fired after the state change** — listeners handle side effects
5. **Every action that touches money must be decimal(15,2)** — never float
6. **Every Livewire action method must call `$this->authorize()`** first
7. **All user-facing text must use `__('key')`** — no hardcoded strings

### Architecture Standards

8. **New business domains → new packages** in `packages/inovcom/`
9. **Cross-module data access → via kernel Contracts** (ClientsApi, ItemsApi) — never import a class from another package directly
10. **New cross-module events → defined in kernel** `app/Events/` — not in a package
11. **Database per module** — each package owns its tables (migrations in the package)
12. **Services are stateless** — no instance variables holding tenant state in services

### UI Standards

13. **Livewire components render in the `layouts.app` layout** — no inline layout decisions
14. **Loading states on all async actions** (`wire:loading`)
15. **Confirmation dialogs on destructive actions** (delete, cancel, refuse)
16. **Flash messages via mckenziearts/laravel-notify** — consistent toast notifications
17. **Mobile-first for field-operations views** — separate layout `layouts.field`
18. **Pagination default: 15 records** (current is inconsistent — some are 10)

### Testing Standards (to be established)

19. **Feature tests for all workflows** (quote acceptance → project creation, invoice overdue → dunning)
20. **Unit tests for all calculations** (quote totals, margin, tax, AR aging)
21. **No tests for Livewire CRUD forms** (too brittle, too low value) — test the underlying services

---

## Appendix: File Structure for New Modules

```
packages/inovcom/maintenance/
├── composer.json
├── src/
│   ├── MaintenanceModule.php           (implements ModuleLifecycle)
│   ├── MaintenanceServiceProvider.php  (uses LazyModuleBoot)
│   ├── Models/
│   │   ├── MaintenanceContract.php     (extends TenantModel, uses WorkflowStateMachine, Auditable)
│   │   ├── MaintenanceOrder.php
│   │   └── Intervention.php
│   ├── Http/Livewire/
│   │   ├── ContractsIndex.php
│   │   ├── ContractForm.php
│   │   ├── OrdersIndex.php
│   │   ├── OrderForm.php
│   │   ├── InterventionForm.php
│   │   └── FieldDashboard.php
│   ├── Services/
│   │   ├── MaintenanceScheduler.php   (generates preventive orders)
│   │   └── SlaTracker.php             (calculates breach status)
│   └── Notifications/
│       ├── OrderAssignedNotification.php
│       └── SlaBreachNotification.php
├── database/migrations/
│   └── [dated migration files]
└── resources/views/livewire/maintenance/
    └── [blade templates]
```

---

*Last updated: 2026-04-04*
*Author: BPROO ERP — Transformation Planning*
*Status: ACTIVE — Implementation in progress*
