<?php

namespace App\Livewire\Tenant;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Livewire\Component;

class Dashboard extends Component
{
    // ── Reactive period selector ──────────────────────────────────────
    public string $period = '30'; // days

    public function setPeriod(string $days): void
    {
        $allowed = ['7', '30', '90'];
        if (in_array($days, $allowed, true)) {
            $this->period = $days;
        }
    }

    public function render()
    {
        $user  = auth('tenant')->user();
        $since = now()->subDays((int) $this->period)->startOfDay();

        // ── Permissions ───────────────────────────────────────────────
        $can = [
            'clients'      => Gate::allows('clients.view'),
            'devis'        => Gate::allows('devis.view'),
            'facturation'  => Gate::allows('facturation.view'),
            'projets'      => Gate::allows('projets.view'),
            'maintenance'  => Gate::allows('maintenance.view'),
            'achats'       => Gate::allows('achats.view'),
        ];

        $db = DB::connection('tenant');

        // ── Finance ───────────────────────────────────────────────────
        $finance = null;
        if ($can['facturation']) {
            $invoices   = $db->table('invoices');
            $finance = [
                'total_billed'    => (float) (clone $invoices)->sum('total_ttc'),
                'total_paid'      => (float) (clone $invoices)->where('status', 'paid')->sum('amount_paid'),
                'amount_due'      => (float) (clone $invoices)->whereIn('status', ['sent', 'overdue'])->sum('amount_due'),
                'overdue_count'   => (clone $invoices)->where('status', 'sent')->whereNotNull('due_date')->where('due_date', '<', now()->toDateString())->count(),
                'draft_count'     => (clone $invoices)->where('status', 'draft')->count(),
                'paid_count'      => (clone $invoices)->where('status', 'paid')->count(),
                'period_billed'   => (float) (clone $invoices)->where('issue_date', '>=', $since)->sum('total_ttc'),
                'period_collected'=> (float) (clone $invoices)->where('status', 'paid')->where('paid_at', '>=', $since)->sum('amount_paid'),
            ];
        }

        // ── Sales pipeline (Devis) ────────────────────────────────────
        $pipeline = null;
        if ($can['devis']) {
            $quotes = $db->table('quotes');
            $total  = (clone $quotes)->count();
            $accepted = (clone $quotes)->where('status', 'accepted')->count();
            $pipeline = [
                'draft'           => (clone $quotes)->where('status', 'draft')->count(),
                'sent'            => (clone $quotes)->where('status', 'sent')->count(),
                'accepted'        => $accepted,
                'refused'         => (clone $quotes)->where('status', 'refused')->count(),
                'total'           => $total,
                'conversion_rate' => $total > 0 ? round($accepted / $total * 100) : 0,
                'period_value'    => (float) (clone $quotes)->where('status', 'accepted')->where('accepted_at', '>=', $since)->sum('total_ttc'),
                'expiring_soon'   => (clone $quotes)->where('status', 'sent')->whereNotNull('valid_until')->whereBetween('valid_until', [now(), now()->addDays(7)])->count(),
            ];
        }

        // ── Projects ──────────────────────────────────────────────────
        $projects = null;
        if ($can['projets']) {
            $proj = $db->table('projects');
            $projects = [
                'planned'      => (clone $proj)->where('status', 'planned')->count(),
                'in_progress'  => (clone $proj)->where('status', 'in_progress')->count(),
                'on_hold'      => (clone $proj)->where('status', 'on_hold')->count(),
                'completed'    => (clone $proj)->where('status', 'completed')->count(),
                'total_budget' => (float) (clone $proj)->whereIn('status', ['planned', 'in_progress', 'on_hold'])->sum('budget'),
                'total_cost'   => (float) (clone $proj)->whereIn('status', ['planned', 'in_progress', 'on_hold'])->sum('actual_cost'),
                'late'         => (clone $proj)->whereIn('status', ['in_progress', 'on_hold'])->whereNotNull('end_date')->where('end_date', '<', now()->toDateString())->count(),
                'recent'       => (clone $proj)->orderByDesc('created_at')->limit(5)->get(),
            ];
        }

        // ── Maintenance ───────────────────────────────────────────────
        $maintenance = null;
        if ($can['maintenance']) {
            $orders = $db->table('maintenance_orders');
            $maintenance = [
                'open'         => (clone $orders)->where('status', 'open')->count(),
                'assigned'     => (clone $orders)->where('status', 'assigned')->count(),
                'in_progress'  => (clone $orders)->where('status', 'in_progress')->count(),
                'done'         => (clone $orders)->where('status', 'done')->count(),
                'sla_breached' => (clone $orders)->whereIn('status', ['open', 'assigned', 'in_progress'])->whereNotNull('due_at')->where('due_at', '<', now())->count(),
                'critical'     => (clone $orders)->whereIn('status', ['open', 'assigned', 'in_progress'])->where('priority', 'critical')->count(),
                'period_closed'=> (clone $orders)->where('status', 'closed')->where('closed_at', '>=', $since)->count(),
                'recent'       => (clone $orders)->whereIn('status', ['open', 'assigned', 'in_progress'])->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'normal' THEN 2 ELSE 3 END")->orderBy('reported_at')->limit(5)->get(),
            ];
        }

        // ── Clients ───────────────────────────────────────────────────
        $clients = null;
        if ($can['clients']) {
            $cli = $db->table('clients');
            $clients = [
                'total'      => (clone $cli)->count(),
                'active'     => (clone $cli)->where('is_active', true)->count(),
                'new_period' => (clone $cli)->where('created_at', '>=', $since)->count(),
            ];
        }

        // ── Purchases ─────────────────────────────────────────────────
        $achats = null;
        if ($can['achats']) {
            $po = $db->table('purchase_orders');
            $achats = [
                'draft'        => (clone $po)->where('status', 'draft')->count(),
                'pending'      => (clone $po)->where('status', 'pending_validation')->count(),
                'validated'    => (clone $po)->whereIn('status', ['validated', 'ordered'])->count(),
                'period_total' => (float) (clone $po)->where('created_at', '>=', $since)->sum('total_ht'),
            ];
        }

        return view('livewire.tenant.dashboard', [
            'can'         => $can,
            'user'        => $user,
            'finance'     => $finance,
            'pipeline'    => $pipeline,
            'projects'    => $projects,
            'maintenance' => $maintenance,
            'clients'     => $clients,
            'achats'      => $achats,
        ])->layout('layouts.app', [
            'title'    => __('Tableau de bord'),
            'subtitle' => __('Vue d\'ensemble de l\'activité'),
        ]);
    }
}
