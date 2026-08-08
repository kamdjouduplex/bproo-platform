<?php

namespace Pressing\Http\Controllers;

use App\Services\TenantBrandingService;
use App\Services\TenantManager;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Pressing\Support\PressingAgenceContext;
use Pressing\Support\PressingWorkflow;

class LavageRelancesPrintController
{
    public function __invoke(Request $request): View
    {
        $this->authorize('pressing_lavage_relances.print');

        $stageName = PressingWorkflow::STAGE_LAVAGE;

        $sinceDate = (string) ($request->query('since') ?: now()->subMonths(1)->toDateString());
        $since = Carbon::parse($sinceDate)->startOfDay();

        $canViewAllAgences = PressingAgenceContext::canViewAllAgences();
        $agenceFilter = $canViewAllAgences
            ? ($request->query('agence') ? (int) $request->query('agence') : null)
            : PressingAgenceContext::userAgenceId();

        $onlyActive = $request->query('onlyActive');
        $onlyActive = $onlyActive === null ? true : filter_var((string) $onlyActive, FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE) ?? true;

        $search = (string) ($request->query('search') ?? '');

        $likeOp = DB::connection('tenant')->getDriverName() === 'pgsql' ? 'ilike' : 'like';
        $term = '%' . trim($search) . '%';

        $latestLavageByClient = DB::connection('tenant')->table('pressing_orders as po')
            ->join('order_stage_history as osh', 'osh.order_id', '=', 'po.id')
            ->select([
                'po.client_id as client_id',
                // Date du dépôt (reception) sur les commandes ayant atteint l’étape "Lavage".
                DB::raw('MAX(po.received_at) as last_lavage_at'),
            ])
            ->where('osh.stage_name', $stageName)
            ->groupBy('po.client_id');

        $clientsQuery = DB::connection('tenant')->table('pressing_clients as pc')
            ->leftJoinSub($latestLavageByClient, 'll', 'll.client_id', '=', 'pc.id')
            ->leftJoin('agences as ag', 'ag.id', '=', 'pc.agence_id')
            ->select([
                'pc.id',
                'pc.code',
                'pc.first_name',
                'pc.last_name',
                'pc.whatsapp',
                'pc.phone',
                'pc.is_active',
                'pc.agence_id',
                DB::raw('ag.name as agence_name'),
                DB::raw('ll.last_lavage_at as last_lavage_at'),
            ])
            ->when($onlyActive, fn ($q) => $q->where('pc.is_active', true))
            ->when($agenceFilter, fn ($q) => $q->where('pc.agence_id', $agenceFilter))
            ->when($search !== '', function ($q) use ($likeOp, $term) {
                $q->where(function ($inner) use ($likeOp, $term) {
                    $inner->where('pc.last_name', $likeOp, $term)
                        ->orWhere('pc.first_name', $likeOp, $term)
                        ->orWhere('pc.whatsapp', $likeOp, $term)
                        ->orWhere('pc.phone', $likeOp, $term)
                        ->orWhere('pc.code', $likeOp, $term);
                });
            })
            ->where(function ($q) use ($since) {
                $q->whereNull('ll.last_lavage_at')
                    ->orWhere('ll.last_lavage_at', '<', $since);
            })
            ->orderByRaw('CASE WHEN ll.last_lavage_at IS NULL THEN 0 ELSE 1 END, ll.last_lavage_at ASC');

        $clients = $clientsQuery->get();

        $clientIds = $clients->pluck('id')->filter()->values()->all();
        $lastOrderByClient = [];

        if (! empty($clientIds)) {
            $latestLavageOrderAtByClient = DB::connection('tenant')->table('order_stage_history as osh')
                ->join('pressing_orders as po', 'po.id', '=', 'osh.order_id')
                ->select([
                    'po.client_id as client_id',
                    DB::raw('MAX(po.received_at) as last_lavage_at'),
                ])
                ->where('osh.stage_name', $stageName)
                ->whereIn('po.client_id', $clientIds)
                ->groupBy('po.client_id');

            $lastOrders = DB::connection('tenant')->table('order_stage_history as osh')
                ->join('pressing_orders as po', 'po.id', '=', 'osh.order_id')
                ->joinSub($latestLavageOrderAtByClient, 'x', function ($join) {
                    $join->on('x.client_id', '=', 'po.client_id')
                        ->on('x.last_lavage_at', '=', 'po.received_at');
                })
                ->select([
                    'po.client_id as client_id',
                    'po.number as last_order_number',
                    DB::raw('po.received_at as last_lavage_received_at'),
                ])
                ->where('osh.stage_name', $stageName)
                ->get();

            $lastOrderByClient = $lastOrders
                ->mapWithKeys(fn ($r) => [
                    (int) $r->client_id => [
                        'order_number' => $r->last_order_number,
                        'moved_at' => $r->last_lavage_received_at,
                    ],
                ])
                ->all();
        }

        foreach ($clients as $client) {
            $client->last_order_number = $lastOrderByClient[(int) $client->id]['order_number'] ?? null;
        }

        $tenant = app(TenantManager::class)->tenant();
        $settings = app(TenantBrandingService::class)->documentSettings($tenant);

        $tenantCode = request()->query('tenant') ?? session('tenant_code');

        return view('pressing::print.lavage-relances', [
            'settings' => $settings,
            'tenantCode' => $tenantCode,
            'sinceDate' => $sinceDate,
            'sinceLabel' => Carbon::parse($sinceDate)->format('d/m/Y'),
            'docDate' => now()->format('d/m/Y'),
            'docLabel' => 'RELANCE DÉPÔT LAVAGE',
            'docNumber' => '—',
            'clients' => $clients,
        ]);
    }

    private function authorize(string $permission): void
    {
        $user = Auth::guard('tenant')->user();
        if (! $user) {
            abort(403, 'Action non autorisée.');
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return;
        }

        abort_unless(
            method_exists($user, 'hasPermission') && $user->hasPermission($permission),
            403,
            'Action non autorisée.'
        );
    }
}

