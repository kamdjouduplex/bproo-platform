@php $tenantCode ??= null; @endphp
<div class="page-body">

    <div class="flex flex-wrap items-center justify-between gap-3 mb-4">
        <a class="btn btn-secondary btn-sm"
           href="{{ route('tenant.logistique.index', ['tenant' => $tenantCode]) }}">← Livraisons</a>
        @if($canCreate)
        <button type="button" wire:click="openCreate" class="btn btn-primary">+ Nouveau véhicule</button>
        @endif
    </div>

    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-4">
        @forelse($vehicles as $v)
        <div class="card" wire:key="veh-{{ $v->id }}">
            <div class="flex items-start justify-between mb-3">
                <div class="w-10 h-10 rounded-lg bg-slate-100 flex items-center justify-center flex-shrink-0 text-lg">
                    {{ match($v->type) { 'truck' => '🚛', 'van' => '🚐', 'pickup' => '🛻', 'motorcycle' => '🏍️', default => '🚗' } }}
                </div>
                <span class="{{ $v->statusBadgeClass() }}">{{ $v->statusLabel() }}</span>
            </div>
            <h3 class="font-semibold text-slate-800">{{ $v->name }}</h3>
            <p class="text-xs text-slate-400 mb-1">{{ $v->typeLabel() }} · {{ $v->plate_number }}</p>
            @if($v->capacity_kg)
            <p class="text-xs text-slate-400 mb-3">Capacité : {{ number_format((float)$v->capacity_kg, 0, ',', ' ') }} kg</p>
            @endif
            <div class="flex items-center gap-2 border-t border-slate-100 pt-3 mt-3">
                @if($canEdit)
                <button type="button" wire:click="openEdit({{ $v->id }})"
                        class="btn btn-secondary btn-sm">Modifier</button>
                @endif
                @if($canDelete)
                <button type="button" wire:click="delete({{ $v->id }})"
                        wire:confirm="Supprimer ce véhicule ?"
                        class="btn btn-sm text-red-500 hover:text-red-700 hover:bg-red-50">Supprimer</button>
                @endif
            </div>
        </div>
        @empty
        <div class="sm:col-span-3 card py-12 text-center text-slate-400">Aucun véhicule enregistré.</div>
        @endforelse
    </div>

    {{-- ── Modal ─────────────────────────────────────────────────────── --}}
    @if($showModal)
    <div class="fixed inset-0 z-50 flex items-center justify-center bg-black/40 p-4" wire:click.self="closeModal">
        <div class="bg-white rounded-xl shadow-xl w-full max-w-lg">
            <div class="flex items-center justify-between px-6 py-4 border-b border-slate-100">
                <h3 class="text-base font-semibold text-slate-800">
                    {{ $editVehicleId ? 'Modifier le véhicule' : 'Nouveau véhicule' }}
                </h3>
                <button type="button" wire:click="closeModal" class="text-slate-400 hover:text-slate-600">✕</button>
            </div>
            <form wire:submit="save" class="p-6 space-y-4">
                <div class="grid grid-cols-2 gap-4">
                    <div class="field col-span-2">
                        <label class="field-label">Nom <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="name" class="input" placeholder="Toyota Hilux">
                        @error('name') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label class="field-label">Immatriculation <span class="text-red-500">*</span></label>
                        <input type="text" wire:model="plate_number" class="input" placeholder="CM-001-AA">
                        @error('plate_number') <p class="field-error">{{ $message }}</p> @enderror
                    </div>
                    <div class="field">
                        <label class="field-label">Type</label>
                        <select wire:model="type" class="input">
                            @foreach($types as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div class="field">
                        <label class="field-label">Capacité (kg)</label>
                        <input type="number" wire:model="capacity_kg" class="input" min="0" placeholder="0">
                    </div>
                    <div class="field">
                        <label class="field-label">Statut</label>
                        <select wire:model="status" class="input">
                            @foreach($statuses as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                            @endforeach
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
