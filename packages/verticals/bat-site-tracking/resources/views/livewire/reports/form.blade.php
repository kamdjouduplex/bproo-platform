@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $isValidated = $status === 'validated';
    $statusBg = match($status) {
        'draft'     => 'bg-slate-100 text-slate-600',
        'submitted' => 'bg-blue-100 text-blue-700',
        'validated' => 'bg-emerald-100 text-emerald-700',
        default     => 'bg-slate-100 text-slate-500',
    };
@endphp
<div>

    {{-- ── Action bar (edit mode) ──────────────────────────────────────────── --}}
    @if ($isEdit)
    <div class="flex items-center justify-between gap-3 bg-white border border-slate-200 rounded-xl px-4 py-3 mb-4">
        <div class="flex items-center gap-2.5 flex-wrap">
            <span class="inline-block px-2.5 py-0.5 rounded-full text-[11px] font-semibold {{ $statusBg }}">{{ $report->statusLabel() }}</span>
            <span class="font-mono text-[12px] font-semibold text-slate-500">{{ $report->code }}</span>
            @if ($report->pv_signed)
                <span class="inline-block px-2 py-0.5 rounded-full text-[11px] font-medium bg-emerald-100 text-emerald-700">✓ {{ __('PV signé') }}</span>
            @endif
        </div>
        <div class="flex items-center gap-2 flex-wrap">
            @if ($status === 'draft')
                <button type="button" class="btn btn-primary btn-sm" wire:click="submit"
                    wire:loading.attr="disabled"
                    wire:confirm="{{ __('Soumettre ce rapport ?') }}">
                    {{ __('Soumettre') }}
                </button>
            @endif
            @if ($status === 'submitted' && $this->tenantCan('suivi.validate'))
                <button type="button" class="btn btn-success btn-sm" wire:click="validateReport"
                    wire:loading.attr="disabled"
                    wire:confirm="{{ __('Valider ce rapport de chantier ?') }}">
                    {{ __('Valider') }}
                </button>
            @endif
        </div>
    </div>
    @endif

    {{-- ── Section 1 : Informations générales ─────────────────────────────── --}}
    <section class="card mb-4">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Informations générales') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Projet') }} <span class="text-red-500">*</span></label>
                <select class="input" wire:model.live="project_id" {{ $isValidated ? 'disabled' : '' }}>
                    <option value="">{{ __('-- Sélectionner un projet --') }}</option>
                    @foreach ($projects as $p)
                        <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->title }}</option>
                    @endforeach
                </select>
                @error('project_id') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Client') }}</label>
                <select class="input" wire:model="client_id" {{ $isValidated ? 'disabled' : '' }}>
                    <option value="">{{ __('-- Auto depuis projet --') }}</option>
                    @foreach ($clients as $c)
                        <option value="{{ $c->id }}">{{ $c->name }}</option>
                    @endforeach
                </select>
            </div>

            <div class="field">
                <label class="field-label">{{ __('Date du rapport') }} <span class="text-red-500">*</span></label>
                <input type="date" class="input" wire:model="report_date" {{ $isValidated ? 'disabled' : '' }}>
                @error('report_date') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Responsable') }}</label>
                <select class="input" wire:model="assigned_to" {{ $isValidated ? 'disabled' : '' }}>
                    <option value="">{{ __('-- Non assigné --') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
            </div>
        </div>
    </section>

    {{-- ── Section 2 : Conditions & effectif ──────────────────────────────── --}}
    <section class="card mb-4">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Conditions & effectif') }}</h2>
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Météo') }}</label>
                <select class="input" wire:model="weather" {{ $isValidated ? 'disabled' : '' }}>
                    <option value="sunny">☀️ {{ __('Ensoleillé') }}</option>
                    <option value="cloudy">⛅ {{ __('Nuageux') }}</option>
                    <option value="rainy">🌧️ {{ __('Pluvieux') }}</option>
                    <option value="windy">💨 {{ __('Venteux') }}</option>
                    <option value="other">🌡️ {{ __('Autre') }}</option>
                </select>
            </div>

            <div class="field">
                <label class="field-label">{{ __('Effectif (personnes)') }}</label>
                <input type="number" class="input" wire:model="workers_count" min="0" {{ $isValidated ? 'disabled' : '' }}>
                @error('workers_count') <span class="field-error">{{ $message }}</span> @enderror
            </div>

            <div class="field">
                <label class="field-label">{{ __('Avancement global (%)') }}</label>
                <input type="number" class="input" wire:model="progress_percent"
                       min="0" max="100" {{ $isValidated ? 'disabled' : '' }}>
                <p class="text-[11px] text-slate-400 mt-1">{{ __('Ce % sera enregistré dans l\'historique d\'avancement du projet.') }}</p>
                @error('progress_percent') <span class="field-error">{{ $message }}</span> @enderror
                @if ($progress_percent > 0)
                    <div class="bg-slate-100 rounded h-1.5 mt-1.5">
                        <div class="bg-indigo-600 h-1.5 rounded transition-[width] duration-300" style="width:{{ min($progress_percent, 100) }}%;"></div>
                    </div>
                @endif
            </div>
        </div>
    </section>

    {{-- ── Section 3 : Travaux réalisés ────────────────────────────────────── --}}
    <section class="card mb-4">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Compte-rendu journalier') }}</h2>
        <div class="grid grid-cols-1 gap-4">
            <div class="field">
                <label class="field-label">{{ __('Travaux réalisés') }}</label>
                <textarea class="input" rows="4" wire:model="work_done"
                    placeholder="{{ __("Décrire les travaux effectués aujourd'hui…") }}"
                    {{ $isValidated ? 'disabled' : '' }}></textarea>
            </div>

            <div class="field">
                <label class="field-label">{{ __('Incidents / Blocages') }}</label>
                <textarea class="input" rows="3" wire:model="issues"
                    placeholder="{{ __('Problèmes rencontrés, retards, manques matériaux…') }}"
                    {{ $isValidated ? 'disabled' : '' }}></textarea>
            </div>

            <div class="field">
                <label class="field-label">{{ __('Prochaines étapes') }}</label>
                <textarea class="input" rows="3" wire:model="next_steps"
                    placeholder="{{ __('Travaux prévus pour le lendemain…') }}"
                    {{ $isValidated ? 'disabled' : '' }}></textarea>
            </div>

            <div class="field">
                <label class="field-label">{{ __('Notes internes') }}</label>
                <textarea class="input" rows="2" wire:model="notes"
                    placeholder="{{ __('Informations complémentaires…') }}"
                    {{ $isValidated ? 'disabled' : '' }}></textarea>
            </div>
        </div>
    </section>

    {{-- ── Section 4 : PV de réception client ─────────────────────────────── --}}
    <section class="card mb-4">
        <h2 class="text-base font-semibold text-slate-800 mb-4">{{ __('Procès-verbal client') }}</h2>
        <div class="grid grid-cols-1 gap-4">
            <div class="field">
                <label class="flex items-center gap-2.5 cursor-pointer">
                    <input type="checkbox" wire:model.live="pv_signed"
                        {{ $isValidated ? 'disabled' : '' }}
                        class="rounded border-slate-300 text-blue-600 w-4 h-4 cursor-pointer">
                    <span class="text-[13px] text-slate-700">{{ __('PV signé par le client') }}</span>
                </label>
            </div>

            @if ($pv_signed)
                <div class="field max-w-sm">
                    <label class="field-label">{{ __('Nom du signataire') }}</label>
                    <input type="text" class="input" wire:model="pv_client_name"
                        placeholder="{{ __('Nom et prénom du client signataire') }}"
                        {{ $isValidated ? 'disabled' : '' }}>
                    @error('pv_client_name') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                @if ($isEdit && $report->pv_signed_at)
                    <div class="flex items-center gap-2">
                        <span class="field-label">{{ __('Signé le :') }}</span>
                        <span class="text-[13px] text-slate-500">{{ $report->pv_signed_at->format('d/m/Y H:i') }}</span>
                    </div>
                @endif
            @endif
        </div>
    </section>

    {{-- ── Section 5 : Documents (edit mode) ───────────────────────────────── --}}
    @if ($isEdit && $reportId)
        <section class="card mb-4">
            <livewire:inovcom-dms.entity-documents
                attachable-type="site_report"
                :attachable-id="$reportId"
                :key="'rpt-docs-'.$reportId"
            />
        </section>
    @endif

    {{-- ── Actions ──────────────────────────────────────────────────────────── --}}
    @if (!$isValidated)
        <div class="flex items-center gap-2 mt-4 pt-0">
            <a href="{{ route('tenant.suivi.index', ['tenant' => $tenantCode]) }}" class="btn btn-secondary">
                {{ __('Annuler') }}
            </a>
            <button type="button" class="btn btn-primary"
                wire:click="save"
                wire:loading.attr="disabled"
                wire:target="save">
                <span wire:loading.remove wire:target="save">
                    {{ $isEdit ? __('Enregistrer') : __('Créer le rapport') }}
                </span>
                <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
            </button>
        </div>
    @endif

</div>
