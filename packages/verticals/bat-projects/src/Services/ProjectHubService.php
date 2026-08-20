<?php

namespace InovCom\Projets\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use InovCom\Projets\Models\Project;
use InovCom\Projets\Support\ProjectHubSnapshot;

class ProjectHubService
{
    public function snapshot(Project $project, string $tenantCode): ProjectHubSnapshot
    {
        $db = DB::connection('tenant');
        $id = (int) $project->id;
        $currency = (string) config('inovcom.currency', 'XOF');

        $invoices = $this->invoiceTotals($db, $id);
        $budget = (float) ($project->budget ?? 0);
        $actualCost = (float) ($project->actual_cost ?? 0);
        $billed = $invoices['billed'];
        $collected = $invoices['collected'];
        $amountDue = $invoices['due'];
        $margin = $collected - $actualCost;
        $marginPct = $collected > 0 ? round($margin / $collected * 100, 1) : null;
        $overBudget = $budget > 0 && $actualCost > $budget;
        $late = in_array($project->status, ['in_progress', 'on_hold'], true)
            && $project->end_date
            && $project->end_date->isPast();

        $links = $this->links($project, $tenantCode, $db, $id);

        return new ProjectHubSnapshot(
            project: $project,
            currency: $currency,
            budget: $budget,
            actualCost: $actualCost,
            billed: $billed,
            collected: $collected,
            amountDue: $amountDue,
            margin: $margin,
            marginPct: $marginPct,
            overBudget: $overBudget,
            late: $late,
            openTaskCount: $this->countWhere($db, 'project_tasks', [
                ['project_id', $id],
                ['status', '!=', 'done'],
            ]),
            memberCount: $this->countWhere($db, 'project_members', [['project_id', $id]]),
            latestReport: $this->latestReport($db, $id),
            recentPurchaseOrders: $this->recentPurchaseOrders($db, $id),
            recentInvoices: $this->recentInvoices($db, $id),
            recentReports: $this->recentReports($db, $id),
            links: $links,
        );
    }

    /**
     * @return array{billed: float, collected: float, due: float, count: int}
     */
    private function invoiceTotals($db, int $projectId): array
    {
        $empty = ['billed' => 0.0, 'collected' => 0.0, 'due' => 0.0, 'count' => 0];
        if (!$this->hasTable($db, 'invoices')) {
            return $empty;
        }

        $row = $db->table('invoices')
            ->where('project_id', $projectId)
            ->where('status', '!=', 'cancelled')
            ->selectRaw('COUNT(*) as cnt')
            ->selectRaw('COALESCE(SUM(total_ttc), 0) as billed')
            ->selectRaw("COALESCE(SUM(CASE WHEN status = 'paid' THEN amount_paid ELSE 0 END), 0) as collected")
            ->selectRaw('COALESCE(SUM(amount_due), 0) as amount_due')
            ->first();

        return [
            'billed'    => (float) ($row->billed ?? 0),
            'collected' => (float) ($row->collected ?? 0),
            'due'       => (float) ($row->amount_due ?? 0),
            'count'     => (int) ($row->cnt ?? 0),
        ];
    }

    /**
     * @return array<string, array{count: int, route: string|null, create: string|null}>
     */
    private function links(Project $project, string $tenantCode, $db, int $id): array
    {
        $params = ['tenant' => $tenantCode];
        $withProject = ['tenant' => $tenantCode, 'project' => $id];

        $quoteCount = $project->quote_id ? 1 : 0;
        $quoteRoute = ($project->quote_id && Route::has('tenant.devis.show'))
            ? route('tenant.devis.show', ['tenant' => $tenantCode, 'quote' => $project->quote_id])
            : (Route::has('tenant.devis.index') ? route('tenant.devis.index', $params) : null);

        $invoiceTotals = $this->invoiceTotals($db, $id);

        return [
            'devis' => [
                'count'  => $quoteCount,
                'route'  => $quoteRoute,
                'create' => null,
            ],
            'achats' => [
                'count'  => $this->countWhere($db, 'purchase_orders', [['project_id', $id]]),
                'route'  => Route::has('tenant.achats.index') ? route('tenant.achats.index', $withProject) : null,
                'create' => Route::has('tenant.achats.create') ? route('tenant.achats.create', $withProject) : null,
            ],
            'factures' => [
                'count'  => $invoiceTotals['count'],
                'route'  => Route::has('tenant.facturation.index') ? route('tenant.facturation.index', $withProject) : null,
                'create' => Route::has('tenant.facturation.create') ? route('tenant.facturation.create', $withProject) : null,
            ],
            'rapports' => [
                'count'  => $this->countWhere($db, 'site_reports', [['project_id', $id]]),
                'route'  => Route::has('tenant.suivi.board')
                    ? route('tenant.suivi.board', ['tenant' => $tenantCode, 'project' => $id, 'tab' => 'rapports'])
                    : null,
                'create' => Route::has('tenant.suivi.create') ? route('tenant.suivi.create', $withProject) : null,
            ],
            'documents' => [
                'count'  => $this->documentCount($db, $id),
                'route'  => Route::has('tenant.dms.index') ? route('tenant.dms.index', $params) : null,
                'create' => null,
            ],
            'livraisons' => [
                'count'  => $this->countWhere($db, 'deliveries', [['project_id', $id]]),
                'route'  => Route::has('tenant.logistique.index') ? route('tenant.logistique.index', $withProject) : null,
                'create' => Route::has('tenant.logistique.create') ? route('tenant.logistique.create', $withProject) : null,
            ],
            'taches' => [
                'count'  => $this->countWhere($db, 'project_tasks', [['project_id', $id]]),
                'route'  => Route::has('tenant.suivi.board')
                    ? route('tenant.suivi.board', ['tenant' => $tenantCode, 'project' => $id])
                    : null,
                'create' => null,
            ],
        ];
    }

    private function documentCount($db, int $projectId): int
    {
        if (!$this->hasTable($db, 'document_attachments')) {
            return 0;
        }

        return (int) $db->table('document_attachments')
            ->where('attachable_type', 'project')
            ->where('attachable_id', $projectId)
            ->count();
    }

    private function latestReport($db, int $projectId): ?array
    {
        $rows = $this->recentReports($db, $projectId, 1);

        return $rows[0] ?? null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentReports($db, int $projectId, int $limit = 3): array
    {
        if (!$this->hasTable($db, 'site_reports')) {
            return [];
        }

        return $db->table('site_reports')
            ->where('project_id', $projectId)
            ->orderByDesc('report_date')
            ->orderByDesc('id')
            ->limit($limit)
            ->get(['id', 'code', 'report_date', 'weather', 'workers_count', 'progress_percent', 'status', 'work_done'])
            ->map(fn ($r) => [
                'id'               => (int) $r->id,
                'code'             => $r->code,
                'report_date'      => $r->report_date,
                'weather'          => $r->weather,
                'workers_count'    => (int) $r->workers_count,
                'progress_percent' => (int) $r->progress_percent,
                'status'           => $r->status,
                'work_done'        => $r->work_done,
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentPurchaseOrders($db, int $projectId): array
    {
        if (!$this->hasTable($db, 'purchase_orders')) {
            return [];
        }

        $query = $db->table('purchase_orders as po')
            ->where('po.project_id', $projectId)
            ->orderByDesc('po.id')
            ->limit(4)
            ->select(['po.id', 'po.code', 'po.status', 'po.total_ht', 'po.created_at']);

        if ($this->hasTable($db, 'suppliers')) {
            $query->leftJoin('suppliers as s', 's.id', '=', 'po.supplier_id')
                ->addSelect('s.name as supplier_name');
        }

        return $query->get()->map(fn ($r) => [
            'id'            => (int) $r->id,
            'code'          => $r->code,
            'status'        => $r->status,
            'total_ht'      => (float) $r->total_ht,
            'supplier_name' => $r->supplier_name ?? null,
        ])->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentInvoices($db, int $projectId): array
    {
        if (!$this->hasTable($db, 'invoices')) {
            return [];
        }

        return $db->table('invoices')
            ->where('project_id', $projectId)
            ->where('status', '!=', 'cancelled')
            ->orderByDesc('issue_date')
            ->orderByDesc('id')
            ->limit(4)
            ->get(['id', 'code', 'title', 'status', 'invoice_type', 'total_ttc', 'amount_due'])
            ->map(fn ($r) => [
                'id'           => (int) $r->id,
                'code'         => $r->code,
                'title'        => $r->title,
                'status'       => $r->status,
                'invoice_type' => $r->invoice_type,
                'total_ttc'    => (float) $r->total_ttc,
                'amount_due'   => (float) $r->amount_due,
            ])
            ->all();
    }

    /**
     * @param  array<int, array{0: string, 1: mixed, 2?: mixed}>  $wheres
     */
    private function countWhere($db, string $table, array $wheres): int
    {
        if (!$this->hasTable($db, $table)) {
            return 0;
        }

        $q = $db->table($table);
        foreach ($wheres as $where) {
            if (count($where) === 3) {
                $q->where($where[0], $where[1], $where[2]);
            } else {
                $q->where($where[0], $where[1]);
            }
        }

        return (int) $q->count();
    }

    private function hasTable($db, string $table): bool
    {
        return Schema::connection($db->getName())->hasTable($table);
    }
}
