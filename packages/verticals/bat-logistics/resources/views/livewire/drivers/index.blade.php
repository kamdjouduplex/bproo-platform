@php $tenantCode ??= null; @endphp
<div class="page-body">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <a class="btn btn-secondary btn-sm"
           href="{{ route('tenant.logistique.index', ['tenant' => $tenantCode]) }}">← Livraisons</a>
        @if($canCreate)
        <button type="button" wire:click="openCreate" class="btn btn-primary">+ Nouveau chauffeur</button>
        @endif
    </div>

    <div class="card p-0 overflow-hidden">
        <table class="w-full border-collapse text-[12px]">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50">
                    <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Nom</th>
                    <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Téléphone</th>
                    <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Email</th>
                    <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Permis</th>
                    <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Statut</th>
                    <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">Actions</th>
                </tr>
            </thead>
            <tbody>
                @forelse($drivers as $d)
                <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors" wire:key="drv-{{ $d->id }}">
                    <td class="px-4 py-2.5 font-semibold text-slate-800">{{ $d->name }}</td>
                    <td class="px-4 py-2.5 text-slate-500">{{ $d->phone ?? '—' }}</td>
                    <td class="px-4 py-2.5 text-slate-500">{{ $d->email ?? '—' }}</td>
                    <td class="px-4 py-2.5 font-mono text-[11px] text-slate-400">{{ $d->license_number ?? '—' }}</td>
                    <td class="px-4 py-2.5">
                        <span class="{{ $d->statusBadgeClass() }}">{{ $d->statusLabel() }}</span>
                    </td>
                    <td class="px-4 py-2.5">
                        <div class="flex items-center gap-1">
                            @if($canEdit)
                            <button type="button" wire:click="openEdit({{ $d->id }})"
                                    class="table-action table-action-edit" title="Modifier">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                            </button>
                            @endif
                            @if($canDelete)
                            <button type="button" wire:click="delete({{ $d->id }})"
                                    wire:confirm="Supprimer ce chauffeur ?"
                                    class="table-action table-action-delete" title="Supprimer">
                                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="15" height="15"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                            </button>
                            @endif
                        </div>
                    </td>
                </tr>
                @empty
                <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Aucun chauffeur enregistré.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    {{-- ── Modal ─────────────────────────────────────────────────────── --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeModal">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h3 class="text-base font-semibold text-slate-800">
                    {{ $editDriverId ? 'Modifier le chauffeur' : 'Nouveau chauffeur' }}
                </h3>
                <button type="button" wire:click="closeModal" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <form wire:submit="save" class="p-6 space-y-4">
                <div class="field">
                    <label class="field-label">Nom complet <span class="text-red-500">*</span></label>
                    <input type="text" wire:model="name" class="input" placeholder="Jean DUPONT">
                    @error('name') <p class="field-error">{{ $message }}</p> @enderror
                </div>
                <div class="grid grid-cols-2 gap-4">
                    <div class="field">
                        <label class="field-label">Téléphone</label>
                        <input type="text" wire:model="phone" class="input" placeholder="+237 6XX XXX XXX">
                    </div>
                    <div class="field">
                        <label class="field-label">Email</label>
                        <input type="email" wire:model="email" class="input" placeholder="jean@exemple.com">
                        @error('email') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label class="field-label">N° de permis</label>
                        <input type="text" wire:model="license_number" class="input" placeholder="CM-XXXXXX">
                    </div>
                    <div class="field">
                        <label class="field-label">Statut</label>
                        <select wire:model="status" class="input">
                            <option value="active">Actif</option>
                            <option value="inactive">Inactif</option>
                        </select>
                    </div>
                </div>
                <div class="flex justify-end gap-2 border-t pt-4">
                    <button type="button" wire:click="closeModal" class="btn btn-secondary">Annuler</button>
                    <button type="submit" class="btn btn-primary">Enregistrer</button>
                </div>
            </form>
        </div>
    </div>
    @endif
</div>
