@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $isClosed = $status === 'closed';
    $statusBg = match($status) {
        'planned'     => 'bg-slate-100 text-slate-600',
        'in_progress' => 'bg-blue-100 text-blue-700',
        'on_hold'     => 'bg-amber-100 text-amber-700',
        'completed'   => 'bg-emerald-100 text-emerald-700',
        'closed'      => 'bg-slate-200 text-slate-500',
        default       => 'bg-slate-100 text-slate-500',
    };
    $statusLabel = match($status) {
        'planned'     => __('Planifié'),
        'in_progress' => __('En cours'),
        'on_hold'     => __('En attente'),
        'completed'   => __('Terminé'),
        'closed'      => __('Clôturé'),
        default       => $status,
    };
@endphp
<div>

    {{-- ── Action bar (edit mode) ───────────────────────────────────── --}}
    @if($projectId)
    <div class="flex items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl px-4 py-3 mb-4">
        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold uppercase tracking-wide {{ $statusBg }}">
                {{ $statusLabel }}
            </span>
            <span class="font-mono text-[12px] font-semibold text-slate-500">{{ $code }}</span>

            @if($liveProgress > 0)
                <span class="relative inline-flex items-center bg-slate-200 rounded-full h-5 min-w-[80px] overflow-hidden">
                    <span class="absolute inset-y-0 left-0 bg-blue-500 rounded-full transition-[width] duration-300" style="width:{{ $liveProgress }}%;"></span>
                    <span class="relative z-10 text-[11px] font-semibold text-slate-900 px-2 whitespace-nowrap">{{ number_format($liveProgress, 0) }}%</span>
                </span>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if(in_array('in_progress', $allowedTransitions))
                <button type="button" class="btn btn-primary btn-sm" wire:click="startProject"
                        wire:loading.attr="disabled" wire:target="startProject"
                        wire:confirm="{{ __('Démarrer ce projet ?') }}">
                    {{ __('Démarrer') }}
                </button>
            @endif
            @if(in_array('on_hold', $allowedTransitions))
                <button type="button" class="btn btn-secondary btn-sm" wire:click="holdProject"
                        wire:loading.attr="disabled" wire:target="holdProject"
                        wire:confirm="{{ __('Mettre ce projet en attente ?') }}">
                    {{ __('Mettre en attente') }}
                </button>
            @endif
            @if(in_array('completed', $allowedTransitions))
                <button type="button" class="btn btn-success btn-sm" wire:click="completeProject"
                        wire:loading.attr="disabled" wire:target="completeProject"
                        wire:confirm="{{ __('Marquer ce projet comme terminé ?') }}">
                    {{ __('Terminer') }}
                </button>
            @endif
            @if(in_array('closed', $allowedTransitions))
                <button type="button" class="btn btn-secondary btn-sm" wire:click="closeProject"
                        wire:loading.attr="disabled" wire:target="closeProject"
                        wire:confirm="{{ __('Clôturer définitivement ce projet ?') }}">
                    {{ __('Clôturer') }}
                </button>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Budget / progress summary (edit mode) ───────────────────── --}}
    @if($projectId && ((float)$budget > 0 || $liveActualCost > 0 || $liveProgress > 0))
    <div class="flex flex-wrap gap-4 bg-slate-50 border border-slate-200 rounded-xl px-4 py-2.5 mb-4">
        <div class="flex flex-col gap-0.5">
            <span class="text-[11px] text-slate-500 uppercase tracking-wider">{{ __('Budget') }}</span>
            <span class="text-base font-bold text-slate-900">{{ number_format((float)$budget, 0, ',', ' ') }} XOF</span>
        </div>
        <div class="flex flex-col gap-0.5">
            <span class="text-[11px] text-slate-500 uppercase tracking-wider">{{ __('Coût engagé') }}</span>
            <span class="text-base font-bold {{ $liveActualCost > (float)$budget && (float)$budget > 0 ? 'text-red-600' : 'text-slate-900' }}">
                {{ number_format($liveActualCost, 0, ',', ' ') }} XOF
            </span>
        </div>
        @if((float)$budget > 0 && $liveActualCost > 0)
        <div class="flex flex-col gap-0.5">
            <span class="text-[11px] text-slate-500 uppercase tracking-wider">{{ __('Consommé') }}</span>
            <span class="text-base font-bold {{ $liveActualCost > (float)$budget ? 'text-red-600' : 'text-emerald-600' }}">
                {{ number_format($liveActualCost / (float)$budget * 100, 1) }}%
            </span>
        </div>
        @endif
        <div class="flex flex-col gap-0.5">
            <span class="text-[11px] text-slate-500 uppercase tracking-wider">{{ __('Avancement') }}</span>
            <div class="flex items-center gap-2">
                <span class="text-base font-bold text-slate-900">{{ number_format($liveProgress, 0) }}%</span>
                @if($liveProgress > 0)
                <span class="relative inline-flex items-center bg-slate-200 rounded-full h-4 w-20 overflow-hidden">
                    <span class="absolute inset-y-0 left-0 bg-blue-500 rounded-full" style="width:{{ min($liveProgress, 100) }}%;"></span>
                </span>
                @endif
            </div>
        </div>
    </div>
    @endif

    <section class="card">
        <h2 class="text-base font-semibold text-slate-800 mb-5">
            {{ $projectId
                ? ($isPrestation ? __('Modifier la prestation') : __('Modifier le projet'))
                : ($isPrestation ? __('Nouvelle prestation') : __('Nouveau projet')) }}
        </h2>

        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            {{-- Code --}}
            @if($projectId)
                <div class="field">
                    <label class="field-label">{{ __('Code') }}</label>
                    <input class="input bg-slate-50 text-slate-500" wire:model="code" readonly disabled>
                    @error('code') <span class="field-error">{{ $message }}</span> @enderror
                </div>
            @else
                <div class="field">
                    <label class="field-label">{{ __('Code') }}</label>
                    <input class="input bg-slate-50 text-slate-400" value="—" readonly disabled>
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Généré automatiquement (ex. PRJ00001)') }}</span>
                </div>
            @endif

            {{-- Quote --}}
            <div class="field">
                <label class="field-label">{{ __('Devis accepté') }}</label>
                <select class="input" wire:model.live="quote_id">
                    <option value="0">— {{ __('Aucun devis') }} —</option>
                    @foreach ($acceptedQuotes as $q)
                        <option value="{{ $q->id }}">{{ $q->code }} – {{ \Illuminate\Support\Str::limit($q->title, 40) }}</option>
                    @endforeach
                </select>
                @error('quote_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Client --}}
            <div class="field">
                <label class="field-label">{{ __('Client') }} <span class="text-red-500">*</span></label>
                <select class="input" wire:model="client_id">
                    <option value="0">— {{ __('Choisir un client') }} —</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->name }} ({{ $c->code }})</option>
                    @endforeach
                </select>
                @error('client_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Title --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ $isPrestation ? __('Titre de la prestation') : __('Titre du projet') }} <span class="text-red-500">*</span></label>
                <input class="input" wire:model="title" placeholder="{{ __('Intitulé du projet') }}">
                @error('title') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Type (verrouillé selon le contexte chantier / prestation) --}}
            <div class="field">
                <label class="field-label">{{ __('Type d\'exécution') }}</label>
                @if($projectTypeLocked)
                    <div class="flex items-center min-h-10 px-3.5 bg-slate-50 border border-slate-200 rounded-lg">
                        <span class="inline-block px-2 py-0.5 rounded text-[11px] font-semibold {{ $projectTypeBadge }}">{{ $projectTypeLabel }}</span>
                    </div>
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __('Défini automatiquement (chantier ou prestation).') }}</span>
                @else
                    <select class="input" wire:model="project_type">
                        @foreach(\InovCom\Kernel\Support\ServiceCatalog::executionTypes() as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                @endif
                @error('project_type') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Priority --}}
            <div class="field">
                <label class="field-label">{{ __('Priorité') }}</label>
                <select class="input" wire:model="priority">
                    <option value="low">{{ __('Basse') }}</option>
                    <option value="normal">{{ __('Normale') }}</option>
                    <option value="high">{{ __('Haute') }}</option>
                    <option value="urgent">{{ __('Urgente') }}</option>
                </select>
                @error('priority') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Status --}}
            <div class="field">
                <label class="field-label">{{ __('Statut') }}</label>
                <select class="input" wire:model="status" @if($projectId) disabled @endif>
                    <option value="planned">{{ __('Planifié') }}</option>
                    <option value="in_progress">{{ __('En cours') }}</option>
                    <option value="on_hold">{{ __('En attente') }}</option>
                    <option value="completed">{{ __('Terminé') }}</option>
                    <option value="closed">{{ __('Clôturé') }}</option>
                </select>
                @if($projectId)
                    <span class="text-[11px] text-slate-400 mt-0.5">{{ __("Utilisez les boutons d'action pour changer le statut.") }}</span>
                @endif
            </div>

            {{-- Budget --}}
            <div class="field">
                <label class="field-label">{{ __('Budget (XOF)') }}</label>
                <input class="input" type="number" step="1000" min="0" wire:model="budget" placeholder="0">
                @error('budget') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Start date --}}
            <div class="field">
                <label class="field-label">{{ __('Date de début') }}</label>
                <input class="input" wire:model="start_date" type="date">
                @error('start_date') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- End date --}}
            <div class="field">
                <label class="field-label">{{ __('Date de fin (délais contractuels)') }}</label>
                <input class="input" wire:model="end_date" type="date">
                @error('end_date') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            {{-- Assigned to --}}
            <div class="field">
                <label class="field-label">{{ __('Chef de projet') }}</label>
                <select class="input" wire:model="assigned_to">
                    <option value="">{{ __('— Non assigné —') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>

            {{-- Contract number --}}
            <div class="field">
                <label class="field-label">{{ __('N° de contrat / marché') }}</label>
                <input class="input" wire:model="contract_number" placeholder="{{ __('Référence contractuelle') }}">
            </div>

            {{-- Site address --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Adresse du chantier') }}</label>
                <input class="input" wire:model="site_address" placeholder="{{ __("Lieu d'exécution des travaux") }}">
            </div>

            {{-- Notes --}}
            <div class="field sm:col-span-2">
                <label class="field-label">{{ __('Notes') }}</label>
                <textarea class="input" wire:model="notes" rows="3" placeholder="{{ __('Description, remarques, contraintes...') }}"></textarea>
            </div>
        </div>

        {{-- ── Documents ───────────────────────────────────────────── --}}
        <div class="mt-5 pt-5 border-t border-slate-100">
            @if ($projectId && class_exists(\InovCom\Dms\Http\Livewire\EntityDocuments::class))
                <livewire:inovcom-dms.entity-documents
                    attachable-type="project"
                    :attachable-id="$projectId"
                    :key="'project-docs-'.$projectId"
                />
            @else
                <div class="flex items-center gap-3 px-4 py-3 bg-slate-50 border border-dashed border-slate-200 rounded-xl text-[13px] text-slate-400">
                    <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-4 h-4 flex-shrink-0 text-slate-300"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    {{ $isPrestation ? __('Enregistrez la prestation pour y attacher des documents (plans, photos, PV…).') : __('Enregistrez le projet pour y attacher des documents (plans, photos, PV…).') }}
                </div>
            @endif
        </div>

        <div class="flex items-center gap-2 mt-6 pt-4 border-t border-slate-100">
            <a class="btn btn-secondary" href="{{ $projectId
                ? route('tenant.projets.show', ['tenant' => $tenantCode, 'project' => $projectId])
                : route($isPrestation ? 'tenant.prestations.index' : 'tenant.projets.index', ['tenant' => $tenantCode]) }}">
                {{ __('Retour') }}
            </a>
            <button class="btn btn-primary" wire:click="save"
                    wire:loading.attr="disabled" wire:target="save">
                <span wire:loading.remove wire:target="save">{{ $projectId ? __('Mettre à jour') : __('Enregistrer') }}</span>
                <span wire:loading wire:target="save">{{ __('Enregistrement...') }}</span>
            </button>
        </div>
    </section>
</div>
