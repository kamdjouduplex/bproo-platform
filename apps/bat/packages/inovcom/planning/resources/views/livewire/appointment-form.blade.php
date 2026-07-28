@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $usersById  = collect($users)->keyBy('id');
@endphp
<div>
<div class="card">

    {{-- ═══════════════════════════════════════════════════════════════
         Section 1 — Informations générales
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3 mb-5">
        <div class="flex-shrink-0 w-7 h-7 rounded-lg bg-indigo-50 flex items-center justify-center">
            <svg width="14" height="14" fill="none" stroke="#4338ca" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>
            </svg>
        </div>
        <h3 class="text-[12px] font-bold text-slate-700 uppercase tracking-widest">{{ __('1 — Informations générales') }}</h3>
        <div class="flex-1 h-px bg-slate-100"></div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

        <div class="field">
            <label class="field-label">{{ __('Type') }}</label>
            <select class="input" wire:model="type">
                <option value="visit_terrain">{{ __('Visite terrain') }}</option>
                <option value="reunion">{{ __('Réunion') }}</option>
                <option value="maintenance">{{ __('Maintenance') }}</option>
                <option value="project_milestone">{{ __('Jalon projet') }}</option>
                <option value="other">{{ __('Autre') }}</option>
            </select>
        </div>

        <div class="field">
            <label class="field-label">{{ __('Statut') }}</label>
            <select class="input" wire:model.live="status">
                <option value="scheduled">{{ __('Planifié') }}</option>
                <option value="confirmed">{{ __('Confirmé') }}</option>
                <option value="done">{{ __('Terminé') }}</option>
                <option value="cancelled">{{ __('Annulé') }}</option>
            </select>
            @error('status') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field sm:col-span-2">
            <label class="field-label">{{ __('Titre') }} <span class="text-red-500">*</span></label>
            <input type="text" class="input" wire:model="title"
                   placeholder="{{ __('Ex : Visite chantier Dupont, Réunion de lancement…') }}">
            @error('title') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label class="field-label">{{ __('Début') }} <span class="text-red-500">*</span></label>
            <input type="datetime-local" class="input" wire:model="start_at">
            @error('start_at') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field">
            <label class="field-label">{{ __('Fin') }} <span class="text-red-500">*</span></label>
            <input type="datetime-local" class="input" wire:model="end_at">
            @error('end_at') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        <div class="field sm:col-span-2">
            <label class="field-label">{{ __('Lieu') }}</label>
            <input type="text" class="input" wire:model="location"
                   placeholder="{{ __('Adresse, bâtiment, salle de réunion…') }}">
        </div>

        <div class="field sm:col-span-2">
            <label class="field-label">{{ __('Description') }}</label>
            <textarea class="input" wire:model="description" rows="2"
                      placeholder="{{ __('Objectif, contexte, ordre du jour…') }}"></textarea>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         Section 2 — Liaisons
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3 mb-5 pt-2 border-t border-slate-100">
        <div class="flex-shrink-0 w-7 h-7 rounded-lg bg-sky-50 flex items-center justify-center">
            <svg width="14" height="14" fill="none" stroke="#0284c7" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M10 13a5 5 0 007.54.54l3-3a5 5 0 00-7.07-7.07l-1.72 1.71"/><path d="M14 11a5 5 0 00-7.54-.54l-3 3a5 5 0 007.07 7.07l1.71-1.71"/>
            </svg>
        </div>
        <h3 class="text-[12px] font-bold text-slate-700 uppercase tracking-widest">{{ __('2 — Liaisons') }}</h3>
        <div class="flex-1 h-px bg-slate-100"></div>
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mb-6">

        <div class="field">
            <label class="field-label">{{ __('Responsable') }}</label>
            <select class="input" wire:model="assigned_to">
                <option value="">{{ __('— Non assigné —') }}</option>
                @foreach ($users as $u)
                    <option value="{{ $u->id }}">{{ $u->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label">{{ __('Client') }}</label>
            <select class="input" wire:model="client_id">
                <option value="">{{ __('— Aucun client —') }}</option>
                @foreach ($clients as $c)
                    <option value="{{ $c->id }}">{{ $c->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label">{{ __('Projet lié') }}</label>
            <select class="input" wire:model="project_id">
                <option value="">{{ __('— Aucun projet —') }}</option>
                @foreach ($projects as $p)
                    <option value="{{ $p->id }}">{{ $p->code }} — {{ $p->title }}</option>
                @endforeach
            </select>
        </div>

        <div class="field">
            <label class="field-label">{{ __('Ordre de maintenance') }}</label>
            <select class="input" wire:model="maintenance_order_id">
                <option value="">{{ __('— Aucun ordre —') }}</option>
                @foreach ($maintenanceOrders as $mo)
                    <option value="{{ $mo->id }}">{{ $mo->code }} — {{ \Illuminate\Support\Str::limit($mo->title, 40) }}</option>
                @endforeach
            </select>
        </div>

    </div>

    {{-- ═══════════════════════════════════════════════════════════════
         Section 3 — Participants
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3 mb-5 pt-2 border-t border-slate-100">
        <div class="flex-shrink-0 w-7 h-7 rounded-lg bg-violet-50 flex items-center justify-center">
            <svg width="14" height="14" fill="none" stroke="#7c3aed" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M17 21v-2a4 4 0 00-4-4H5a4 4 0 00-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 00-3-3.87"/><path d="M16 3.13a4 4 0 010 7.75"/>
            </svg>
        </div>
        <h3 class="text-[12px] font-bold text-slate-700 uppercase tracking-widest">{{ __('3 — Participants') }}</h3>
        <span class="text-[11px] text-slate-400 font-normal">{{ __('Utilisateurs internes ou personnes externes') }}</span>
        <div class="flex-1 h-px bg-slate-100"></div>
        @if (!$showAddParticipant)
        <button type="button" wire:click="$set('showAddParticipant', true)"
                class="flex-shrink-0 inline-flex items-center gap-1 text-[12px] font-semibold text-indigo-600 hover:text-indigo-800">
            <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg>
            {{ __('Ajouter') }}
        </button>
        @endif
    </div>

    {{-- Participant list --}}
    @if (count($participants) > 0)
    <ul class="mb-4 space-y-1">
        @foreach ($participants as $i => $p)
            @php
                $displayName  = $p['is_external'] ? $p['name'] : ($usersById->get((int)$p['user_id'])?->name ?? '?');
                $displayEmail = $p['email'] ?? '';
                $displayPhone = $p['phone'] ?? '';
                $initial      = mb_strtoupper(mb_substr($displayName, 0, 1)) ?: '?';
                $palette      = [
                    ['#eef2ff','#4338ca'], ['#f0fdf4','#16a34a'], ['#fef3c7','#d97706'],
                    ['#fce7f3','#be185d'], ['#f0f9ff','#0284c7'], ['#fff7ed','#c2410c'],
                ];
                $color = $palette[abs(crc32($displayName)) % count($palette)];
            @endphp
            <li wire:key="participant-{{ $i }}"
                class="flex items-center gap-3 px-3 py-2 rounded-lg border border-slate-100 bg-slate-50">
                {{-- Avatar --}}
                <div class="flex-shrink-0 w-8 h-8 rounded-full flex items-center justify-center text-[12px] font-bold select-none"
                     style="background:{{ $color[0] }};color:{{ $color[1] }};">
                    {{ $initial }}
                </div>
                {{-- Info --}}
                <div class="flex-1 min-w-0">
                    <span class="block text-[13px] font-semibold text-slate-800">{{ $displayName }}</span>
                    @if ($displayEmail || $displayPhone)
                        <span class="block text-[11px] text-slate-400">
                            {{ implode(' · ', array_filter([$displayEmail, $displayPhone])) }}
                        </span>
                    @endif
                </div>
                {{-- Type badge --}}
                <span class="flex-shrink-0 text-[10px] font-bold px-1.5 py-0.5 rounded {{ $p['is_external'] ? 'bg-amber-100 text-amber-700' : 'bg-indigo-100 text-indigo-700' }}">
                    {{ $p['is_external'] ? __('Externe') : __('Interne') }}
                </span>
                {{-- Remove --}}
                <button type="button" wire:click="removeParticipant({{ $i }})"
                        class="flex-shrink-0 p-1 rounded text-slate-400 hover:text-red-500 hover:bg-red-50 transition-colors"
                        title="{{ __('Retirer') }}">
                    <svg width="13" height="13" fill="none" stroke="currentColor" stroke-width="2.2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </li>
        @endforeach
    </ul>
    @elseif (!$showAddParticipant)
        <p class="text-[12px] text-slate-400 mb-4">{{ __('Aucun participant ajouté. Utilisez le bouton ci-dessus pour en ajouter.') }}</p>
    @endif

    {{-- Add participant inline form --}}
    @if ($showAddParticipant)
    <div class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-xl">
        <p class="text-[12px] font-bold text-slate-600 uppercase tracking-wide mb-3">{{ __('Ajouter un participant') }}</p>

        {{-- Type toggle --}}
        <div class="flex gap-2 mb-4">
            <button type="button"
                    wire:click="$set('newParticipantType','user')"
                    class="{{ $newParticipantType === 'user' ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm' }}">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="inline-block mr-1"><path stroke-linecap="round" stroke-linejoin="round" d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
                {{ __('Utilisateur interne') }}
            </button>
            <button type="button"
                    wire:click="$set('newParticipantType','external')"
                    class="{{ $newParticipantType === 'external' ? 'btn btn-primary btn-sm' : 'btn btn-secondary btn-sm' }}">
                <svg width="12" height="12" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="inline-block mr-1"><circle cx="12" cy="12" r="10"/><path stroke-linecap="round" d="M12 8v4l3 3"/></svg>
                {{ __('Personne externe') }}
            </button>
        </div>

        @if ($newParticipantType === 'user')
            <div class="field mb-3">
                <label class="field-label">{{ __('Choisir un utilisateur') }}</label>
                <select class="input" wire:model="newParticipantUserId">
                    <option value="">{{ __('— Sélectionner —') }}</option>
                    @foreach ($users as $u)
                        <option value="{{ $u->id }}">{{ $u->name }}</option>
                    @endforeach
                </select>
                @error('newParticipantUserId') <span class="field-error">{{ $message }}</span> @enderror
            </div>
        @else
            <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 mb-3">
                <div class="field sm:col-span-3">
                    <label class="field-label">{{ __('Nom complet') }} <span class="text-red-500">*</span></label>
                    <input type="text" class="input" wire:model="newParticipantName" placeholder="Jean Dupont">
                    @error('newParticipantName') <span class="field-error">{{ $message }}</span> @enderror
                </div>
                <div class="field">
                    <label class="field-label">{{ __('Email') }}</label>
                    <input type="email" class="input" wire:model="newParticipantEmail" placeholder="jean@exemple.com">
                </div>
                <div class="field">
                    <label class="field-label">{{ __('Téléphone') }}</label>
                    <input type="text" class="input" wire:model="newParticipantPhone" placeholder="+225 07 00 00 00">
                </div>
            </div>
        @endif

        <div class="flex gap-2 pt-1">
            <button type="button" wire:click="addParticipant" class="btn btn-primary btn-sm">
                {{ __('Confirmer l\'ajout') }}
            </button>
            <button type="button" wire:click="$set('showAddParticipant', false)" class="btn btn-secondary btn-sm">
                {{ __('Annuler') }}
            </button>
        </div>
    </div>
    @else
        <div class="mb-6"></div>
    @endif

    {{-- ═══════════════════════════════════════════════════════════════
         Section 4 — Notes & Compte-rendu
    ════════════════════════════════════════════════════════════════════ --}}
    <div class="flex items-center gap-3 mb-5 pt-2 border-t border-slate-100">
        <div class="flex-shrink-0 w-7 h-7 rounded-lg bg-emerald-50 flex items-center justify-center">
            <svg width="14" height="14" fill="none" stroke="#059669" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24">
                <path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/><path stroke-linecap="round" d="M9 13h6M9 17h4"/>
            </svg>
        </div>
        <h3 class="text-[12px] font-bold text-slate-700 uppercase tracking-widest">{{ __('4 — Notes & Compte-rendu') }}</h3>
        <div class="flex-1 h-px bg-slate-100"></div>
    </div>

    <div class="grid grid-cols-1 gap-4">

        <div class="field">
            <label class="field-label">{{ __('Notes internes') }}</label>
            <textarea class="input" wire:model="notes" rows="3"
                      placeholder="{{ __('Informations supplémentaires, rappels, points d\'attention…') }}"></textarea>
        </div>

        {{-- Report — always visible, required when status = done --}}
        <div class="field">
            <label class="field-label flex items-center gap-2">
                {{ __('Compte-rendu / Rapport de visite') }}
                @if ($status === 'done')
                    <span class="text-[10px] font-bold px-1.5 py-0.5 rounded bg-red-100 text-red-600">
                        {{ __('Requis pour clôturer') }}
                    </span>
                @endif
            </label>
            @if ($status === 'done')
            <div class="mb-2 px-3 py-2 bg-amber-50 border border-amber-200 rounded-lg text-[12px] text-amber-700 flex items-start gap-2">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24" class="flex-shrink-0 mt-0.5"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v4m0 4h.01M10.29 3.86L1.82 18a2 2 0 001.71 3h16.94a2 2 0 001.71-3L13.71 3.86a2 2 0 00-3.42 0z"/></svg>
                {{ __('Le statut "Terminé" exige un compte-rendu. Décrivez ce qui s\'est passé pendant ce rendez-vous.') }}
            </div>
            @endif
            <textarea class="input {{ $status === 'done' ? 'border-amber-300 focus:border-amber-400' : '' }}"
                      wire:model="report" rows="5"
                      placeholder="{{ __('Résumé des échanges, décisions prises, actions à suivre, observations terrain…') }}"></textarea>
            @error('report') <span class="field-error">{{ $message }}</span> @enderror
        </div>

        {{-- Pièces jointes au compte-rendu --}}
        <div class="field">
            <label class="field-label flex items-center gap-2">
                <svg width="14" height="14" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" viewBox="0 0 24 24"><path d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                {{ __('Pièces jointes') }}
            </label>
            @if ($isEdit && $appointment?->id && class_exists(\InovCom\Dms\Http\Livewire\EntityDocuments::class))
                <livewire:inovcom-dms.entity-documents
                    attachable-type="appointment"
                    :attachable-id="$appointment->id"
                    :key="'apt-docs-' . $appointment->id"
                />
            @elseif (!$isEdit)
                <div class="flex items-center gap-3 px-4 py-3 bg-slate-50 border border-dashed border-slate-200 rounded-xl text-[13px] text-slate-400">
                    <svg width="16" height="16" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                    {{ __('Créez d\'abord le rendez-vous pour pouvoir y attacher des fichiers.') }}
                </div>
            @endif
        </div>

    </div>

    {{-- Footer --}}
    <div class="flex items-center gap-2 mt-8 pt-4 border-t border-slate-100">
        <a href="{{ route('tenant.planning.index', ['tenant' => $tenantCode]) }}" class="btn btn-secondary">
            {{ __('Annuler') }}
        </a>
        <button type="button" class="btn btn-primary"
                wire:click="save"
                wire:loading.attr="disabled"
                wire:target="save">
            <span wire:loading.remove wire:target="save">{{ $isEdit ? __('Enregistrer') : __('Créer le rendez-vous') }}</span>
            <span wire:loading wire:target="save">{{ __('Enregistrement…') }}</span>
        </button>
    </div>

</div>
</div>
