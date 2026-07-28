<div class="page-body">

    {{-- ── Profile header ──────────────────────────────────────────────────── --}}
    <div class="card p-6 mb-6">
        <div class="flex flex-col sm:flex-row sm:items-start gap-5">

            {{-- Avatar --}}
            <div class="flex-shrink-0 w-16 h-16 rounded-full {{ $employee->avatarColor() }} flex items-center justify-center text-white text-2xl font-bold">
                {{ $employee->initials() }}
            </div>

            {{-- Main info --}}
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2 mb-1">
                    <h1 class="text-xl font-bold text-slate-800">{{ $employee->fullName() }}</h1>
                    <span class="badge {{ $employee->statusBadgeClass() }}">{{ $employee->statusLabel() }}</span>
                    <span class="badge badge-secondary">{{ $employee->contractLabel() }}</span>
                </div>
                <p class="text-slate-500 mb-3">{{ $employee->position ?? '—' }}
                    @if($employee->department)
                        · <span class="text-slate-400">{{ $employee->department }}</span>
                    @endif
                </p>
                <div class="flex flex-wrap gap-x-6 gap-y-1 text-sm text-slate-600">
                    <span>📅 Embauché le {{ $employee->hire_date->format('d/m/Y') }}</span>
                    @if($employee->email)
                        <span>✉️ {{ $employee->email }}</span>
                    @endif
                    @if($employee->phone)
                        <span>📞 {{ $employee->phone }}</span>
                    @endif
                    <span class="font-medium text-slate-700">
                        💰 {{ number_format((float)$employee->base_salary, 0, ',', ' ') }} FCFA / mois
                    </span>
                </div>
            </div>

            {{-- Edit button --}}
            @if($canEdit)
            <a href="{{ route('tenant.rh.edit', ['employee' => $employee->id, 'tenant' => $tenantCode]) }}"
               class="btn btn-secondary btn-sm flex-shrink-0">
                <svg class="w-4 h-4 mr-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                          d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
                Modifier
            </a>
            @endif

        </div>
    </div>

    {{-- ── Quick stats ──────────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-4 gap-3 mb-6">
        <div class="card p-4 text-center">
            <div class="text-2xl font-bold text-slate-800">{{ $payslips->count() }}</div>
            <div class="text-xs text-slate-500 mt-1">Fiches de paie</div>
        </div>
        <div class="card p-4 text-center">
            <div class="text-2xl font-bold text-emerald-600">
                {{ $payslips->where('status', 'paid')->count() }}
            </div>
            <div class="text-xs text-slate-500 mt-1">Paiements effectués</div>
        </div>
        <div class="card p-4 text-center">
            <div class="text-2xl font-bold text-blue-600">{{ $leaveStatsYear }}</div>
            <div class="text-xs text-slate-500 mt-1">Jours de congé {{ now()->year }}</div>
        </div>
        <div class="card p-4 text-center">
            @if($pendingLeaves > 0)
            <div class="text-2xl font-bold text-amber-500">{{ $pendingLeaves }}</div>
            <div class="text-xs text-amber-600 mt-1">Congé(s) en attente</div>
            @else
            <div class="text-2xl font-bold text-slate-300">0</div>
            <div class="text-xs text-slate-400 mt-1">Demandes en attente</div>
            @endif
        </div>
    </div>

    {{-- ── Tabs ─────────────────────────────────────────────────────────────── --}}
    <div class="flex border-b border-slate-200 mb-6 gap-1">
        <button wire:click="$set('activeTab', 'infos')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                       {{ $activeTab === 'infos'
                            ? 'border-blue-600 text-blue-700'
                            : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Informations
        </button>
        <button wire:click="$set('activeTab', 'payslips')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                       {{ $activeTab === 'payslips'
                            ? 'border-blue-600 text-blue-700'
                            : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Fiches de paie
            @if($payslips->count())
            <span class="ml-1 text-xs bg-slate-100 text-slate-600 px-1.5 py-0.5 rounded-full">{{ $payslips->count() }}</span>
            @endif
        </button>
        <button wire:click="$set('activeTab', 'leaves')"
                class="px-4 py-2.5 text-sm font-medium border-b-2 transition-colors
                       {{ $activeTab === 'leaves'
                            ? 'border-blue-600 text-blue-700'
                            : 'border-transparent text-slate-500 hover:text-slate-700' }}">
            Congés
            @if($pendingLeaves > 0)
            <span class="ml-1 text-xs bg-amber-100 text-amber-700 px-1.5 py-0.5 rounded-full">{{ $pendingLeaves }}</span>
            @endif
        </button>
    </div>

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- ── TAB: Informations ───────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'infos')
    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">

        <div class="card p-5 space-y-4">
            <h3 class="font-semibold text-slate-700 text-sm uppercase tracking-wide">Contrat</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between">
                    <dt class="text-slate-500">Type</dt>
                    <dd class="font-medium text-slate-800">{{ $employee->contractLabel() }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Date d'embauche</dt>
                    <dd class="font-medium text-slate-800">{{ $employee->hire_date->format('d/m/Y') }}</dd>
                </div>
                @if($employee->end_date)
                <div class="flex justify-between">
                    <dt class="text-slate-500">Fin de contrat</dt>
                    <dd class="font-medium text-slate-800">{{ $employee->end_date->format('d/m/Y') }}</dd>
                </div>
                @endif
                <div class="flex justify-between">
                    <dt class="text-slate-500">Salaire de base</dt>
                    <dd class="font-semibold text-slate-800">
                        {{ number_format((float)$employee->base_salary, 0, ',', ' ') }} FCFA
                    </dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Statut</dt>
                    <dd><span class="badge {{ $employee->statusBadgeClass() }}">{{ $employee->statusLabel() }}</span></dd>
                </div>
            </dl>
        </div>

        <div class="card p-5 space-y-4">
            <h3 class="font-semibold text-slate-700 text-sm uppercase tracking-wide">Coordonnées</h3>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500 flex-shrink-0">Email</dt>
                    <dd class="font-medium text-slate-800 truncate">{{ $employee->email ?? '—' }}</dd>
                </div>
                <div class="flex justify-between">
                    <dt class="text-slate-500">Téléphone</dt>
                    <dd class="font-medium text-slate-800">{{ $employee->phone ?? '—' }}</dd>
                </div>
                <div class="flex justify-between gap-2">
                    <dt class="text-slate-500 flex-shrink-0">IBAN</dt>
                    <dd class="font-medium text-slate-800 font-mono text-xs truncate">{{ $employee->iban ?? '—' }}</dd>
                </div>
            </dl>
        </div>

        @if($employee->notes)
        <div class="card p-5 sm:col-span-2">
            <h3 class="font-semibold text-slate-700 text-sm uppercase tracking-wide mb-2">Notes</h3>
            <p class="text-sm text-slate-600 whitespace-pre-line">{{ $employee->notes }}</p>
        </div>
        @endif

    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- ── TAB: Fiches de paie ──────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'payslips')
    <div>

        {{-- Action bar --}}
        @if($canPayroll)
        <div class="flex justify-end mb-4">
            <button wire:click="openCreatePayslipModal" class="btn btn-primary">
                + Créer une fiche de paie
            </button>
        </div>
        @endif

        @if($payslips->isEmpty())
        <div class="card p-10 text-center text-slate-500">
            <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M9 12h6m-6 4h6m2 5H7a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h5.586a1 1 0 0 1 .707.293l5.414 5.414a1 1 0 0 1 .293.707V19a2 2 0 0 1-2 2z"/>
            </svg>
            <p class="font-medium">Aucune fiche de paie</p>
            <p class="text-sm mt-1">Créez la première fiche de paie de cet employé.</p>
        </div>
        @else
        <div class="card overflow-hidden">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 border-b border-slate-200">
                    <tr>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Période</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-600">Brut</th>
                        <th class="text-right px-4 py-3 font-medium text-slate-600">Net</th>
                        <th class="text-center px-4 py-3 font-medium text-slate-600">Statut</th>
                        <th class="text-left px-4 py-3 font-medium text-slate-600">Payé le</th>
                        <th class="px-4 py-3"></th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach($payslips as $ps)
                    <tr wire:key="ps-{{ $ps->id }}" class="hover:bg-slate-50 transition-colors">
                        <td class="px-4 py-3 font-medium text-slate-800">
                            {{ $ps->periodLabel() }}
                            <div class="text-xs text-slate-400 font-normal">{{ $ps->code }}</div>
                        </td>
                        <td class="px-4 py-3 text-right text-slate-700">
                            {{ number_format((float)$ps->gross_salary, 0, ',', ' ') }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold text-slate-800">
                            {{ number_format((float)$ps->net_salary, 0, ',', ' ') }} FCFA
                        </td>
                        <td class="px-4 py-3 text-center">
                            <span class="badge {{ $ps->statusBadgeClass() }}">{{ $ps->statusLabel() }}</span>
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            {{ $ps->paid_at ? $ps->paid_at->format('d/m/Y') : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex items-center gap-1 justify-end">

                                {{-- Print button: visible to all who can view --}}
                                <a href="{{ route('tenant.rh.payslip.pdf', ['payslip' => $ps->id, 'tenant' => $tenantCode]) }}"
                                   target="_blank"
                                   title="Imprimer le bulletin de paie"
                                   class="table-action text-blue-500 hover:text-blue-700">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M17 17h2a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h2m2 4h6a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2H9a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2zm1-4h4v4H10v-4z"/>
                                    </svg>
                                </a>

                                @if($canPayroll)
                                @if($ps->status === 'draft')
                                <button wire:click="validatePayslip({{ $ps->id }})"
                                        class="btn btn-sm btn-secondary text-xs px-2 py-1">
                                    Valider
                                </button>
                                <button wire:click="openEditPayslipModal({{ $ps->id }})"
                                        class="table-action table-action-edit">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                                    </svg>
                                </button>
                                @endif
                                @if($ps->status === 'validated')
                                <button wire:click="markPayslipPaid({{ $ps->id }})"
                                        class="btn btn-sm btn-success text-xs px-2 py-1">
                                    Marquer payé
                                </button>
                                @endif
                                <button wire:click="deletePayslip({{ $ps->id }})"
                                        wire:confirm="Supprimer cette fiche de paie ?"
                                        class="table-action table-action-delete">
                                    <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                              d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16"/>
                                    </svg>
                                </button>
                                @endif

                            </div>
                        </td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
        @endif

    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- ── TAB: Congés ─────────────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($activeTab === 'leaves')
    <div>

        {{-- Action bar --}}
        <div class="flex justify-end mb-4">
            @if($canCreate)
            <button wire:click="openCreateLeaveModal" class="btn btn-primary">
                + Demander un congé
            </button>
            @endif
        </div>

        @if($leaves->isEmpty())
        <div class="card p-10 text-center text-slate-500">
            <svg class="w-10 h-10 mx-auto mb-3 text-slate-300" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5"
                      d="M8 7V3m8 4V3m-9 8h10M5 21h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2H5a2 2 0 0 0-2 2v12a2 2 0 0 0 2 2z"/>
            </svg>
            <p class="font-medium">Aucun congé enregistré</p>
        </div>
        @else
        <div class="space-y-2">
            @foreach($leaves as $leave)
            <div wire:key="leave-{{ $leave->id }}"
                 class="card p-4 flex flex-col sm:flex-row sm:items-center gap-3">

                <div class="flex-1 min-w-0">
                    <div class="flex items-center gap-2 flex-wrap">
                        <span class="font-medium text-slate-800">{{ $leave->typeLabel() }}</span>
                        <span class="badge {{ $leave->statusBadgeClass() }}">{{ $leave->statusLabel() }}</span>
                    </div>
                    <p class="text-sm text-slate-500 mt-0.5">
                        Du {{ $leave->start_date->format('d/m/Y') }}
                        au {{ $leave->end_date->format('d/m/Y') }}
                        · <strong>{{ $leave->days_count }} jour(s)</strong>
                    </p>
                    @if($leave->reason)
                    <p class="text-sm text-slate-500 mt-0.5 italic">{{ $leave->reason }}</p>
                    @endif
                </div>

                <div class="flex items-center gap-1 flex-shrink-0">
                    @if($leave->status === 'pending')
                        @if($canApproveLeave)
                        <button wire:click="approveLeave({{ $leave->id }})"
                                class="btn btn-sm btn-success text-xs px-2 py-1">
                            Approuver
                        </button>
                        <button wire:click="rejectLeave({{ $leave->id }})"
                                class="btn btn-sm btn-danger text-xs px-2 py-1">
                            Refuser
                        </button>
                        @endif
                        @if($canEdit)
                        <button wire:click="openEditLeaveModal({{ $leave->id }})"
                                class="table-action table-action-edit">
                            <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                      d="M11 5H6a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h11a2 2 0 0 0 2-2v-5m-1.414-9.414a2 2 0 1 1 2.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                            </svg>
                        </button>
                        @endif
                    @endif
                    @if($canDelete)
                    <button wire:click="deleteLeave({{ $leave->id }})"
                            wire:confirm="Supprimer cette demande de congé ?"
                            class="table-action table-action-delete">
                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2"
                                  d="M19 7l-.867 12.142A2 2 0 0 1 16.138 21H7.862a2 2 0 0 1-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 0 0-1-1h-4a1 1 0 0 0-1 1v3M4 7h16"/>
                        </svg>
                    </button>
                    @endif
                </div>

            </div>
            @endforeach
        </div>
        @endif

    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- ── PAYSLIP MODAL ────────────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($showPayslipModal)
    <div class="fixed inset-0 z-50 flex items-start justify-center bg-black/50 p-4 pt-8 overflow-y-auto">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-2xl mb-8">

            {{-- Header --}}
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
                <h2 class="text-lg font-semibold text-slate-800">
                    {{ $editPayslipId ? 'Modifier la fiche de paie' : 'Nouvelle fiche de paie' }}
                </h2>
                <button wire:click="closePayslipModal" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-5 space-y-5">

                {{-- Period --}}
                <div class="grid grid-cols-2 gap-4">
                    <div class="field">
                        <label class="field-label">Mois</label>
                        <select wire:model="payMonth" class="input">
                            @foreach($months as $num => $name)
                            <option value="{{ $num }}">{{ $name }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Année</label>
                        <input type="number" wire:model="payYear" class="input" min="2000" max="2100">
                    </div>
                </div>

                {{-- Base salary --}}
                <div class="field">
                    <label class="field-label">Salaire de base (FCFA)</label>
                    <input type="number" wire:model.live="payBaseSalary" class="input" min="0" step="500">
                    @error('payBaseSalary') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                {{-- Additions --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="field-label mb-0">Primes & compléments</label>
                        <button type="button" wire:click="addAdditionRow"
                                class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                            + Ajouter une ligne
                        </button>
                    </div>
                    @if(empty($payAdditions))
                    <p class="text-sm text-slate-400 italic">Aucune prime ajoutée.</p>
                    @else
                    <div class="space-y-2">
                        @foreach($payAdditions as $i => $row)
                        <div wire:key="add-{{ $i }}" class="flex items-center gap-2">
                            <input type="text"
                                   wire:model.live="payAdditions.{{ $i }}.label"
                                   class="input flex-1 input-sm"
                                   placeholder="Ex: Prime de rendement">
                            <input type="number"
                                   wire:model.live="payAdditions.{{ $i }}.amount"
                                   class="input w-36 input-sm text-right"
                                   placeholder="0" min="0" step="500">
                            <button type="button" wire:click="removeAdditionRow({{ $i }})"
                                    class="text-red-400 hover:text-red-600 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                {{-- Deductions --}}
                <div>
                    <div class="flex items-center justify-between mb-2">
                        <label class="field-label mb-0">Retenues & déductions</label>
                        <button type="button" wire:click="addDeductionRow"
                                class="text-xs text-blue-600 hover:text-blue-800 font-medium">
                            + Ajouter une ligne
                        </button>
                    </div>
                    @if(empty($payDeductions))
                    <p class="text-sm text-slate-400 italic">Aucune retenue ajoutée.</p>
                    @else
                    <div class="space-y-2">
                        @foreach($payDeductions as $i => $row)
                        <div wire:key="ded-{{ $i }}" class="flex items-center gap-2">
                            <input type="text"
                                   wire:model.live="payDeductions.{{ $i }}.label"
                                   class="input flex-1 input-sm"
                                   placeholder="Ex: Cotisations sociales">
                            <input type="number"
                                   wire:model.live="payDeductions.{{ $i }}.amount"
                                   class="input w-36 input-sm text-right"
                                   placeholder="0" min="0" step="500">
                            <button type="button" wire:click="removeDeductionRow({{ $i }})"
                                    class="text-red-400 hover:text-red-600 flex-shrink-0">
                                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                                </svg>
                            </button>
                        </div>
                        @endforeach
                    </div>
                    @endif
                </div>

                {{-- Totals --}}
                @php
                    $addTotal   = collect($payAdditions)->sum(fn($r) => (float)($r['amount'] ?? 0));
                    $dedTotal   = collect($payDeductions)->sum(fn($r) => (float)($r['amount'] ?? 0));
                    $gross      = (float)($payBaseSalary ?: 0) + $addTotal;
                    $net        = $gross - $dedTotal;
                @endphp
                <div class="rounded-lg bg-slate-50 border border-slate-200 p-4 space-y-2 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <span>Salaire de base</span>
                        <span>{{ number_format((float)($payBaseSalary ?: 0), 0, ',', ' ') }} FCFA</span>
                    </div>
                    @if($addTotal > 0)
                    <div class="flex justify-between text-emerald-600">
                        <span>+ Primes & compléments</span>
                        <span>+ {{ number_format($addTotal, 0, ',', ' ') }} FCFA</span>
                    </div>
                    @endif
                    <div class="flex justify-between font-medium text-slate-800 border-t border-slate-200 pt-2">
                        <span>Salaire brut</span>
                        <span>{{ number_format($gross, 0, ',', ' ') }} FCFA</span>
                    </div>
                    @if($dedTotal > 0)
                    <div class="flex justify-between text-red-500">
                        <span>− Retenues</span>
                        <span>− {{ number_format($dedTotal, 0, ',', ' ') }} FCFA</span>
                    </div>
                    @endif
                    <div class="flex justify-between font-bold text-lg text-emerald-700 border-t border-slate-200 pt-2">
                        <span>Net à payer</span>
                        <span>{{ number_format($net, 0, ',', ' ') }} FCFA</span>
                    </div>
                </div>

                {{-- Notes --}}
                <div class="field">
                    <label class="field-label">Notes</label>
                    <textarea wire:model="payNotes" class="input min-h-[60px]" placeholder="Remarques éventuelles…"></textarea>
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-3 p-5 border-t border-slate-200">
                <button wire:click="closePayslipModal" class="btn btn-secondary">Annuler</button>
                <button wire:click="savePayslip" class="btn btn-primary">
                    {{ $editPayslipId ? 'Enregistrer' : 'Créer la fiche' }}
                </button>
            </div>

        </div>
    </div>
    @endif

    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    {{-- ── LEAVE MODAL ──────────────────────────────────────────────────────── --}}
    {{-- ══════════════════════════════════════════════════════════════════════ --}}
    @if($showLeaveModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/50 p-4">
        <div class="bg-white rounded-xl shadow-2xl w-full max-w-md">

            {{-- Header --}}
            <div class="flex items-center justify-between p-5 border-b border-slate-200">
                <h2 class="text-lg font-semibold text-slate-800">
                    {{ $editLeaveId ? 'Modifier le congé' : 'Demande de congé' }}
                </h2>
                <button wire:click="closeLeaveModal" class="text-slate-400 hover:text-slate-600">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>
                    </svg>
                </button>
            </div>

            {{-- Body --}}
            <div class="p-5 space-y-4">

                <div class="field">
                    <label class="field-label">Type de congé</label>
                    <select wire:model="leaveType" class="input">
                        @foreach($leaveTypes as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('leaveType') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="grid grid-cols-2 gap-4">
                    <div class="field">
                        <label class="field-label">Date de début</label>
                        <input type="date" wire:model.live="leaveStart" class="input">
                        @error('leaveStart') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label class="field-label">Date de fin</label>
                        <input type="date" wire:model.live="leaveEnd" class="input">
                        @error('leaveEnd') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                </div>

                <div class="field">
                    <label class="field-label">Durée (jours)</label>
                    <input type="number" wire:model="leaveDays" class="input" min="1">
                    @error('leaveDays') <p class="field-error">{{ $message }}</p> @enderror
                </div>

                <div class="field">
                    <label class="field-label">Motif</label>
                    <textarea wire:model="leaveReason" class="input min-h-[70px]"
                              placeholder="Raison du congé (optionnel)…"></textarea>
                    @error('leaveReason') <p class="field-error">{{ $message }}</p> @enderror
                </div>

            </div>

            {{-- Footer --}}
            <div class="flex justify-end gap-3 p-5 border-t border-slate-200">
                <button wire:click="closeLeaveModal" class="btn btn-secondary">Annuler</button>
                <button wire:click="saveLeave" class="btn btn-primary">
                    {{ $editLeaveId ? 'Enregistrer' : 'Enregistrer la demande' }}
                </button>
            </div>

        </div>
    </div>
    @endif

</div>
