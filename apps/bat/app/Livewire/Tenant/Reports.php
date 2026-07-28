<?php

namespace App\Livewire\Tenant;

use App\Livewire\Concerns\AuthorizesWithTenant;
use Illuminate\Support\Facades\DB;
use Livewire\Component;
use Symfony\Component\HttpFoundation\StreamedResponse;

class Reports extends Component
{
    use AuthorizesWithTenant;

    public string $tab    = 'aging';      // aging | revenue | quotes | rentabilite | technicien
    public string $period = '12';         // months for revenue tab
    public string $year;                  // fiscal year (aging, quotes, technicien)

    public function mount(): void
    {
        $this->tenantAuthorize('reports.view');
        $this->year = (string) now()->year;
    }

    // ── CSV export ────────────────────────────────────────────────────

    public function exportAging(): StreamedResponse
    {
        $rows = $this->agingRows();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            // BOM for Excel UTF-8
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Client', 'Total dû (XOF)', '0-30j', '31-60j', '61-90j', '>90j'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->client_name,
                    number_format($r->total_due, 0, ',', ' '),
                    number_format($r->bucket_0_30, 0, ',', ' '),
                    number_format($r->bucket_31_60, 0, ',', ' '),
                    number_format($r->bucket_61_90, 0, ',', ' '),
                    number_format($r->bucket_90plus, 0, ',', ' '),
                ], ';');
            }
            fclose($out);
        }, 'ar-aging-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportRevenue(): StreamedResponse
    {
        $months = $this->revenueMonths();

        return response()->streamDownload(function () use ($months) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Mois', 'Facturé (XOF)', 'Encaissé (XOF)', 'Nbre factures'], ';');
            foreach ($months as $m) {
                fputcsv($out, [
                    $m->month_label,
                    number_format($m->billed, 0, ',', ' '),
                    number_format($m->collected, 0, ',', ' '),
                    $m->count,
                ], ';');
            }
            fclose($out);
        }, 'revenue-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportQuotes(): StreamedResponse
    {
        $db = DB::connection('tenant');
        $rows = $db->table('quotes as q')
            ->join('clients as c', 'c.id', '=', 'q.client_id')
            ->select(
                'q.code', 'c.name as client_name', 'q.title',
                'q.status', 'q.total_ttc', 'q.created_at', 'q.accepted_at'
            )
            ->whereYear('q.created_at', $this->year)
            ->orderByDesc('q.created_at')
            ->get();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Code', 'Client', 'Titre', 'Statut', 'Montant TTC (XOF)', 'Créé le', 'Accepté le'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->code,
                    $r->client_name,
                    $r->title,
                    $r->status,
                    number_format($r->total_ttc, 0, ',', ' '),
                    $r->created_at ? date('d/m/Y', strtotime($r->created_at)) : '',
                    $r->accepted_at ? date('d/m/Y', strtotime($r->accepted_at)) : '',
                ], ';');
            }
            fclose($out);
        }, 'devis-' . $this->year . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Data builders ─────────────────────────────────────────────────

    public function agingRows(): \Illuminate\Support\Collection
    {
        $db = DB::connection('tenant');

        // Unpaid invoices joined with clients
        $invoices = $db->table('invoices as i')
            ->join('clients as c', 'c.id', '=', 'i.client_id')
            ->whereIn('i.status', ['sent', 'overdue'])
            ->where('i.amount_due', '>', 0)
            ->select('c.name as client_name', 'i.amount_due', 'i.due_date')
            ->get();

        // Group by client, bucket by days past due
        $grouped = $invoices->groupBy('client_name')->map(function ($rows, $clientName) {
            $b0_30 = $b31_60 = $b61_90 = $b90plus = 0.0;

            foreach ($rows as $inv) {
                // Positive = days overdue; negative or null = still current
                $days = $inv->due_date
                    ? (int) now()->startOfDay()->diffInDays(\Carbon\Carbon::parse($inv->due_date), false) * -1
                    : 0;

                $amt = (float) $inv->amount_due;
                if ($days <= 30)      $b0_30   += $amt;  // current or 1-30 days overdue
                elseif ($days <= 60)  $b31_60  += $amt;
                elseif ($days <= 90)  $b61_90  += $amt;
                else                  $b90plus += $amt;
            }

            return (object) [
                'client_name'   => $clientName,
                'total_due'     => $rows->sum('amount_due'),
                'bucket_0_30'   => $b0_30,
                'bucket_31_60'  => $b31_60,
                'bucket_61_90'  => $b61_90,
                'bucket_90plus' => $b90plus,
            ];
        })->values()->sortByDesc('total_due');

        return $grouped;
    }

    public function revenueMonths(): \Illuminate\Support\Collection
    {
        $months = (int) $this->period;
        $db     = DB::connection('tenant');

        $rows = $db->table('invoices')
            ->selectRaw("
                TO_CHAR(issue_date, 'YYYY-MM') as month_key,
                TO_CHAR(issue_date, 'Mon YYYY') as month_label,
                SUM(total_ttc) as billed,
                SUM(CASE WHEN status = 'paid' THEN amount_paid ELSE 0 END) as collected,
                COUNT(*) as count
            ")
            ->whereNotNull('issue_date')
            ->where('issue_date', '>=', now()->subMonths($months)->startOfMonth())
            ->whereNotIn('status', ['cancelled'])
            ->groupByRaw("TO_CHAR(issue_date, 'YYYY-MM'), TO_CHAR(issue_date, 'Mon YYYY')")
            ->orderBy('month_key')
            ->get();

        return $rows;
    }

    public function quotesData(): array
    {
        $db = DB::connection('tenant');

        $q = $db->table('quotes')->whereYear('created_at', $this->year);

        $total    = (clone $q)->count();
        $draft    = (clone $q)->where('status', 'draft')->count();
        $sent     = (clone $q)->where('status', 'sent')->count();
        $accepted = (clone $q)->where('status', 'accepted')->count();
        $refused  = (clone $q)->where('status', 'refused')->count();

        $valueSent     = (float) (clone $q)->where('status', 'sent')->sum('total_ttc');
        $valueAccepted = (float) (clone $q)->where('status', 'accepted')->sum('total_ttc');

        // Monthly accepted value
        $monthly = $db->table('quotes')
            ->selectRaw("
                TO_CHAR(accepted_at, 'YYYY-MM') as month_key,
                TO_CHAR(accepted_at, 'Mon YYYY') as month_label,
                SUM(total_ttc) as value,
                COUNT(*) as count
            ")
            ->where('status', 'accepted')
            ->whereYear('accepted_at', $this->year)
            ->groupByRaw("TO_CHAR(accepted_at, 'YYYY-MM'), TO_CHAR(accepted_at, 'Mon YYYY')")
            ->orderBy('month_key')
            ->get();

        return compact('total', 'draft', 'sent', 'accepted', 'refused', 'valueSent', 'valueAccepted', 'monthly');
    }

    public function rentabiliteData(): \Illuminate\Support\Collection
    {
        $db = DB::connection('tenant');

        $projects = $db->table('projects as p')
            ->join('clients as c', 'c.id', '=', 'p.client_id')
            ->leftJoin(DB::raw("(
                SELECT project_id,
                       SUM(total_ttc) as billed,
                       SUM(CASE WHEN status = 'paid' THEN amount_paid ELSE 0 END) as collected
                FROM invoices
                WHERE project_id IS NOT NULL AND status != 'cancelled'
                GROUP BY project_id
            ) as inv"), 'inv.project_id', '=', 'p.id')
            ->whereNotIn('p.status', ['cancelled'])
            ->select(
                'p.code', 'p.title', 'p.status', 'p.progress_percent',
                'c.name as client_name',
                'p.budget', 'p.actual_cost',
                DB::raw('COALESCE(inv.billed, 0) as billed'),
                DB::raw('COALESCE(inv.collected, 0) as collected')
            )
            ->orderByDesc('p.id')
            ->get();

        return $projects->map(function ($p) {
            $budget     = (float) $p->budget;
            $actualCost = (float) $p->actual_cost;
            $billed     = (float) $p->billed;
            $collected  = (float) $p->collected;
            $margin     = $collected - $actualCost;
            $marginPct  = $collected > 0 ? round($margin / $collected * 100, 1) : null;
            $overBudget = $budget > 0 && $actualCost > $budget;

            return (object) [
                'code'        => $p->code,
                'title'       => $p->title,
                'client_name' => $p->client_name,
                'status'      => $p->status,
                'progress'    => (int) $p->progress_percent,
                'budget'      => $budget,
                'actual_cost' => $actualCost,
                'billed'      => $billed,
                'collected'   => $collected,
                'margin'      => $margin,
                'margin_pct'  => $marginPct,
                'over_budget' => $overBudget,
            ];
        });
    }

    public function technicienData(): \Illuminate\Support\Collection
    {
        $db   = DB::connection('tenant');
        $year = (int) $this->year;

        $rows = $db->table('interventions as i')
            ->join('users as u', 'u.id', '=', 'i.technician_id')
            ->join('maintenance_orders as mo', 'mo.id', '=', 'i.maintenance_order_id')
            ->whereYear('i.scheduled_at', $year)
            ->select(
                'i.technician_id',
                'u.name as technician_name',
                DB::raw('COUNT(*) as total'),
                DB::raw("SUM(CASE WHEN i.status = 'done' THEN 1 ELSE 0 END) as done"),
                DB::raw('COALESCE(SUM(i.duration_minutes), 0) as total_minutes'),
                DB::raw("SUM(CASE WHEN i.status = 'done' AND mo.due_at IS NOT NULL AND i.completed_at > mo.due_at THEN 1 ELSE 0 END) as sla_breached")
            )
            ->groupBy('i.technician_id', 'u.name')
            ->orderByDesc(DB::raw('COUNT(*)'))
            ->get();

        return $rows->map(function ($r) {
            $total    = (int) $r->total;
            $done     = (int) $r->done;
            $totalMin = (int) $r->total_minutes;
            $avgMin   = $done > 0 ? (int) round($totalMin / $done) : null;
            $breached = (int) $r->sla_breached;
            $slaRate  = $total > 0 ? round($breached / $total * 100) : 0;

            return (object) [
                'technician_name' => $r->technician_name,
                'total'           => $total,
                'done'            => $done,
                'total_minutes'   => $totalMin,
                'avg_minutes'     => $avgMin,
                'sla_breached'    => $breached,
                'sla_rate'        => $slaRate,
            ];
        });
    }

    public function exportRentabilite(): StreamedResponse
    {
        $rows = $this->rentabiliteData();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Code', 'Projet', 'Client', 'Statut', 'Budget (XOF)', 'Coût réel (XOF)', 'Facturé (XOF)', 'Encaissé (XOF)', 'Marge (XOF)', 'Marge %'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->code,
                    $r->title,
                    $r->client_name,
                    $r->status,
                    number_format($r->budget, 0, ',', ' '),
                    number_format($r->actual_cost, 0, ',', ' '),
                    number_format($r->billed, 0, ',', ' '),
                    number_format($r->collected, 0, ',', ' '),
                    number_format($r->margin, 0, ',', ' '),
                    $r->margin_pct !== null ? $r->margin_pct . '%' : '—',
                ], ';');
            }
            fclose($out);
        }, 'rentabilite-projets-' . now()->format('Y-m-d') . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function exportTechnicien(): StreamedResponse
    {
        $rows = $this->technicienData();

        return response()->streamDownload(function () use ($rows) {
            $out = fopen('php://output', 'w');
            fputs($out, "\xEF\xBB\xBF");
            fputcsv($out, ['Technicien', 'Total interventions', 'Réalisées', 'Durée totale (h)', 'Durée moy. (min)', 'Hors SLA', 'Taux hors SLA %'], ';');
            foreach ($rows as $r) {
                fputcsv($out, [
                    $r->technician_name,
                    $r->total,
                    $r->done,
                    $r->total_minutes > 0 ? round($r->total_minutes / 60, 1) : 0,
                    $r->avg_minutes ?? '—',
                    $r->sla_breached,
                    $r->sla_rate . '%',
                ], ';');
            }
            fclose($out);
        }, 'rapport-techniciens-' . $this->year . '.csv', [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    // ── Render ────────────────────────────────────────────────────────

    public function render()
    {
        $aging       = $this->tab === 'aging'       ? $this->agingRows()      : collect();
        $revenue     = $this->tab === 'revenue'     ? $this->revenueMonths()  : collect();
        $quotes      = $this->tab === 'quotes'      ? $this->quotesData()     : [];
        $rentabilite = $this->tab === 'rentabilite' ? $this->rentabiliteData(): collect();
        $technicien  = $this->tab === 'technicien'  ? $this->technicienData() : collect();

        // Totals for aging summary bar
        $agingTotals = $aging->count() ? [
            'total'   => $aging->sum('total_due'),
            'b0_30'   => $aging->sum('bucket_0_30'),
            'b31_60'  => $aging->sum('bucket_31_60'),
            'b61_90'  => $aging->sum('bucket_61_90'),
            'b90plus' => $aging->sum('bucket_90plus'),
        ] : null;

        // Max billed for revenue bar scaling
        $maxBilled = $revenue->max('billed') ?: 1;

        return view('livewire.tenant.reports', [
            'aging'        => $aging,
            'agingTotals'  => $agingTotals,
            'revenue'      => $revenue,
            'maxBilled'    => $maxBilled,
            'quotes'       => $quotes,
            'rentabilite'  => $rentabilite,
            'technicien'   => $technicien,
        ])->layout('layouts.app', [
            'title'    => __('Rapports'),
            'subtitle' => __('Analyse financière et commerciale'),
        ]);
    }
}
