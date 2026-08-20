@php
    $progress = (int) ($project->progress_percent ?? 0);
    $indexRoute = $isPrestation ? 'tenant.prestations.index' : 'tenant.projets.index';
    $weatherLabel = [
        'sunny'  => __('Ensoleillé'),
        'cloudy' => __('Nuageux'),
        'rainy'  => __('Pluvieux'),
        'windy'  => __('Venteux'),
        'other'  => __('Autre'),
    ];
    $poStatus = [
        'draft'              => __('Brouillon'),
        'pending_validation' => __('À valider'),
        'validated'          => __('Validé'),
        'ordered'            => __('Commandé'),
        'partially_received' => __('Réception partielle'),
        'received'           => __('Reçu'),
        'cancelled'          => __('Annulé'),
    ];
    $invStatus = [
        'draft'     => __('Brouillon'),
        'sent'      => __('Envoyée'),
        'paid'      => __('Payée'),
        'overdue'   => __('En retard'),
        'cancelled' => __('Annulée'),
    ];
    $linkMeta = [
        'devis'      => ['label' => __('Devis'), 'hint' => __('Marché signé')],
        'achats'     => ['label' => __('Achats'), 'hint' => __('Bons de commande')],
        'factures'   => ['label' => __('Factures'), 'hint' => __('Acomptes & situations')],
        'rapports'   => ['label' => __('Rapports'), 'hint' => __('Journal de chantier')],
        'documents'  => ['label' => __('Documents'), 'hint' => __('Plans, permis, PV')],
        'livraisons' => ['label' => __('Livraisons'), 'hint' => __('Matériaux sur site')],
        'taches'     => ['label' => __('Tâches'), 'hint' => __('Kanban terrain')],
    ];
@endphp
<div class="space-y-5">

    {{-- ── Haut : identité + un geste ──────────────────────────────── --}}
    <div class="bg-white border border-slate-200 rounded-xl px-5 py-4">
        <div class="flex flex-wrap items-start justify-between gap-3">
            <div class="min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <a href="{{ route($indexRoute, ['tenant' => $tenantCode]) }}"
                       class="text-[13px] text-slate-500 hover:text-slate-800">← {{ $isPrestation ? __('Prestations') : __('Chantiers') }}</a>
                    <span class="text-slate-300">·</span>
                    <span class="font-mono text-[13px] font-semibold text-slate-500">{{ $project->code }}</span>
                </div>
                <h1 class="text-[20px] font-semibold text-slate-900 leading-snug">{{ $project->title }}</h1>
                <p class="mt-1 text-[14px] text-slate-600">
                    {{ $project->client?->name ?? __('Client non renseigné') }}
                    @if($project->site_address)
                        <span class="text-slate-400">·</span> {{ $project->site_address }}
                    @endif
                    @if($project->assignedUser)
                        <span class="text-slate-400">·</span> {{ $project->assignedUser->name }}
                    @endif
                </p>
            </div>
            <div class="flex flex-wrap items-center gap-2">
                @if($canEdit)
                    @if(in_array('in_progress', $allowedTransitions))
                        <button type="button" class="btn btn-primary" wire:click="startProject"
                                wire:confirm="{{ __('Démarrer ce chantier ?') }}">{{ __('Démarrer') }}</button>
                    @endif
                    @if(in_array('on_hold', $allowedTransitions))
                        <button type="button" class="btn btn-secondary" wire:click="holdProject"
                                wire:confirm="{{ __('Mettre en attente ?') }}">{{ __('Mettre en attente') }}</button>
                    @endif
                    @if(in_array('completed', $allowedTransitions))
                        <button type="button" class="btn btn-success" wire:click="completeProject"
                                wire:confirm="{{ __('Marquer comme terminé ?') }}">{{ __('Terminer') }}</button>
                    @endif
                    @if(in_array('closed', $allowedTransitions))
                        <button type="button" class="btn btn-secondary" wire:click="closeProject"
                                wire:confirm="{{ __('Clôturer définitivement ?') }}">{{ __('Clôturer') }}</button>
                    @endif
                    <a class="btn btn-secondary" href="{{ route('tenant.projets.edit', ['tenant' => $tenantCode, 'project' => $project->id]) }}">{{ __('Modifier') }}</a>
                @endif
            </div>
        </div>

        <div class="mt-4 flex flex-wrap items-center gap-3">
            <span class="inline-flex px-2.5 py-1 rounded-full text-[12px] font-semibold {{ $statusClass }}">{{ $statusLabel }}</span>
            @if($snapshot->late)
                <span class="inline-flex px-2.5 py-1 rounded-full text-[12px] font-semibold bg-red-100 text-red-800">{{ __('Retard') }}</span>
            @endif
            <div class="flex items-center gap-2 min-w-[160px] flex-1">
                <div class="relative h-2.5 flex-1 bg-slate-100 rounded-full overflow-hidden">
                    <div class="absolute inset-y-0 left-0 {{ $progress >= 100 ? 'bg-emerald-500' : 'bg-blue-600' }} rounded-full"
                         style="width: {{ min($progress, 100) }}%"></div>
                </div>
                <span class="text-[14px] font-semibold text-slate-800 tabular-nums w-10">{{ $progress }}%</span>
            </div>
            <span class="text-[13px] text-slate-500">
                {{ $project->start_date?->format('d/m/Y') ?? '—' }}
                →
                {{ $project->end_date?->format('d/m/Y') ?? '—' }}
            </span>
        </div>
    </div>

    {{-- ── Argent ──────────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-[13px] font-semibold uppercase tracking-wide text-slate-500 mb-2">{{ __('Argent') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-5 gap-3">
            <div class="bg-white border border-slate-200 rounded-xl px-4 py-3">
                <div class="text-[12px] text-slate-500">{{ __('Budget') }}</div>
                <div class="mt-0.5 text-[18px] font-semibold text-slate-900 tabular-nums">{{ $snapshot->money($snapshot->budget) }}</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl px-4 py-3">
                <div class="text-[12px] text-slate-500">{{ __('Coût') }}</div>
                <div class="mt-0.5 text-[18px] font-semibold tabular-nums {{ $snapshot->overBudget ? 'text-red-700' : 'text-slate-900' }}">{{ $snapshot->money($snapshot->actualCost) }}</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl px-4 py-3">
                <div class="text-[12px] text-slate-500">{{ __('Facturé') }}</div>
                <div class="mt-0.5 text-[18px] font-semibold text-slate-900 tabular-nums">{{ $snapshot->money($snapshot->billed) }}</div>
            </div>
            <div class="bg-white border border-slate-200 rounded-xl px-4 py-3">
                <div class="text-[12px] text-slate-500">{{ __('Encaissé') }}</div>
                <div class="mt-0.5 text-[18px] font-semibold text-slate-900 tabular-nums">{{ $snapshot->money($snapshot->collected) }}</div>
                @if($snapshot->amountDue > 0)
                    <div class="text-[12px] text-amber-700 mt-0.5">{{ __('Dû') }} {{ $snapshot->money($snapshot->amountDue) }}</div>
                @endif
            </div>
            <div class="bg-white border border-slate-200 rounded-xl px-4 py-3 col-span-2 sm:col-span-1">
                <div class="text-[12px] text-slate-500">{{ __('Marge') }}</div>
                <div class="mt-0.5 text-[18px] font-semibold tabular-nums {{ $snapshot->margin < 0 ? 'text-red-700' : 'text-emerald-700' }}">
                    {{ $snapshot->money($snapshot->margin) }}
                </div>
                @if($snapshot->marginPct !== null)
                    <div class="text-[12px] text-slate-500">{{ number_format($snapshot->marginPct, 1) }}%</div>
                @endif
            </div>
        </div>
        <p class="mt-2 text-[12px] text-slate-500">{{ __('Coût = bons de commande validés. Heures et frais arriveront ensuite sur ce même écran.') }}</p>
    </div>

    {{-- ── Liens ───────────────────────────────────────────────────── --}}
    <div>
        <h2 class="text-[13px] font-semibold uppercase tracking-wide text-slate-500 mb-2">{{ __('Sur ce chantier') }}</h2>
        <div class="grid grid-cols-2 sm:grid-cols-4 lg:grid-cols-7 gap-2">
            @foreach (['devis', 'achats', 'factures', 'rapports', 'documents', 'livraisons', 'taches'] as $key)
                @php $link = $snapshot->links[$key] ?? ['count' => 0, 'route' => null]; @endphp
                @if($link['route'])
                    <a href="{{ $link['route'] }}"
                       class="bg-white border border-slate-200 rounded-xl px-3 py-3 hover:border-blue-300 hover:bg-slate-50">
                        <div class="text-[22px] font-semibold text-slate-900 tabular-nums leading-none">{{ $link['count'] }}</div>
                        <div class="mt-1 text-[14px] font-medium text-slate-800">{{ $linkMeta[$key]['label'] }}</div>
                        <div class="text-[12px] text-slate-500">{{ $linkMeta[$key]['hint'] }}</div>
                    </a>
                @else
                    <div class="bg-slate-50 border border-dashed border-slate-200 rounded-xl px-3 py-3 opacity-60">
                        <div class="text-[22px] font-semibold text-slate-400 tabular-nums leading-none">{{ $link['count'] }}</div>
                        <div class="mt-1 text-[14px] font-medium text-slate-500">{{ $linkMeta[$key]['label'] }}</div>
                    </div>
                @endif
            @endforeach
        </div>
    </div>

    {{-- ── Terrain ─────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-4">
        <section class="bg-white border border-slate-200 rounded-xl px-5 py-4">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h2 class="text-[15px] font-semibold text-slate-900">{{ __('Terrain') }}</h2>
                @if($snapshot->links['rapports']['create'] ?? null)
                    <a class="btn btn-primary btn-sm" href="{{ $snapshot->links['rapports']['create'] }}">{{ __('Nouveau rapport') }}</a>
                @endif
            </div>
            @if($snapshot->latestReport)
                <p class="text-[14px] text-slate-800">
                    <span class="font-medium">{{ \Illuminate\Support\Carbon::parse($snapshot->latestReport['report_date'])->format('d/m/Y') }}</span>
                    <span class="text-slate-400">·</span>
                    {{ $snapshot->latestReport['workers_count'] }} {{ __('ouvriers') }}
                    <span class="text-slate-400">·</span>
                    {{ $weatherLabel[$snapshot->latestReport['weather']] ?? $snapshot->latestReport['weather'] }}
                    <span class="text-slate-400">·</span>
                    {{ $snapshot->latestReport['progress_percent'] }}%
                </p>
                @if($snapshot->latestReport['work_done'])
                    <p class="mt-2 text-[14px] text-slate-600 line-clamp-3">{{ $snapshot->latestReport['work_done'] }}</p>
                @endif
            @else
                <p class="text-[14px] text-slate-500">{{ __('Aucun rapport de chantier pour l’instant.') }}</p>
            @endif

            @if(count($snapshot->recentReports) > 1)
                <ul class="mt-3 space-y-1.5">
                    @foreach (array_slice($snapshot->recentReports, 1) as $report)
                        <li class="text-[13px] text-slate-600" wire:key="report-{{ $report['id'] }}">
                            {{ \Illuminate\Support\Carbon::parse($report['report_date'])->format('d/m/Y') }}
                            — {{ $report['progress_percent'] }}%
                            — {{ $report['workers_count'] }} {{ __('ouvriers') }}
                        </li>
                    @endforeach
                </ul>
            @endif
            <p class="mt-3 text-[13px] text-slate-500">
                {{ $snapshot->openTaskCount }} {{ __('tâche(s) ouvertes') }}
                <span class="text-slate-300">·</span>
                {{ $snapshot->memberCount }} {{ __('membre(s)') }}
            </p>
        </section>

        <section class="bg-white border border-slate-200 rounded-xl px-5 py-4">
            <div class="flex items-center justify-between gap-2 mb-3">
                <h2 class="text-[15px] font-semibold text-slate-900">{{ __('Achats récents') }}</h2>
                @if($snapshot->links['achats']['create'] ?? null)
                    <a class="btn btn-secondary btn-sm" href="{{ $snapshot->links['achats']['create'] }}">{{ __('Bon de commande') }}</a>
                @endif
            </div>
            @forelse ($snapshot->recentPurchaseOrders as $po)
                <a href="{{ route('tenant.achats.edit', ['tenant' => $tenantCode, 'purchase_order' => $po['id']]) }}"
                   class="flex items-center justify-between gap-2 py-2 border-b border-slate-100 last:border-0 hover:bg-slate-50 -mx-1 px-1 rounded"
                   wire:key="po-{{ $po['id'] }}">
                    <div class="min-w-0">
                        <div class="text-[14px] font-medium text-slate-800">{{ $po['code'] }}</div>
                        <div class="text-[12px] text-slate-500 truncate">{{ $po['supplier_name'] ?: '—' }} · {{ $poStatus[$po['status']] ?? $po['status'] }}</div>
                    </div>
                    <div class="text-[14px] font-semibold text-slate-800 tabular-nums whitespace-nowrap">{{ $snapshot->money($po['total_ht']) }}</div>
                </a>
            @empty
                <p class="text-[14px] text-slate-500">{{ __('Aucun bon de commande lié.') }}</p>
            @endforelse
        </section>
    </div>

    {{-- ── Factures ────────────────────────────────────────────────── --}}
    <section class="bg-white border border-slate-200 rounded-xl px-5 py-4">
        <div class="flex items-center justify-between gap-2 mb-3">
            <h2 class="text-[15px] font-semibold text-slate-900">{{ __('Factures') }}</h2>
            @if($snapshot->links['factures']['create'] ?? null)
                <a class="btn btn-secondary btn-sm" href="{{ $snapshot->links['factures']['create'] }}">{{ __('Nouvelle facture') }}</a>
            @endif
        </div>
        @forelse ($snapshot->recentInvoices as $invoice)
            <a href="{{ route('tenant.facturation.edit', ['tenant' => $tenantCode, 'invoice' => $invoice['id']]) }}"
               class="flex items-center justify-between gap-2 py-2 border-b border-slate-100 last:border-0 hover:bg-slate-50 -mx-1 px-1 rounded"
               wire:key="inv-{{ $invoice['id'] }}">
                <div class="min-w-0">
                    <div class="text-[14px] font-medium text-slate-800">{{ $invoice['code'] }}</div>
                    <div class="text-[12px] text-slate-500 truncate">{{ $invoice['title'] ?: '—' }} · {{ $invStatus[$invoice['status']] ?? $invoice['status'] }}</div>
                </div>
                <div class="text-right">
                    <div class="text-[14px] font-semibold text-slate-800 tabular-nums">{{ $snapshot->money($invoice['total_ttc']) }}</div>
                    @if($invoice['amount_due'] > 0 && $invoice['status'] !== 'paid')
                        <div class="text-[12px] text-amber-700">{{ __('Reste') }} {{ $snapshot->money($invoice['amount_due']) }}</div>
                    @endif
                </div>
            </a>
        @empty
            <p class="text-[14px] text-slate-500">{{ __('Aucune facture sur ce chantier.') }}</p>
        @endforelse
    </section>

    {{-- ── Documents ───────────────────────────────────────────────── --}}
    <section class="bg-white border border-slate-200 rounded-xl px-5 py-4">
        <h2 class="text-[15px] font-semibold text-slate-900 mb-3">{{ __('Documents') }}</h2>
        @if (class_exists(\InovCom\Dms\Http\Livewire\EntityDocuments::class))
            <livewire:inovcom-dms.entity-documents
                attachable-type="project"
                :attachable-id="$project->id"
                :key="'hub-docs-'.$project->id"
            />
        @else
            <p class="text-[14px] text-slate-500">{{ __('Module documents non activé.') }}</p>
        @endif
    </section>
</div>
