<div class="page-body">
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <form wire:submit.prevent="save">
        <section class="card">
            <h2 class="card-title">Ouvrir un ticket</h2>
            <div class="form-grid">
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="field-label">Titre *</label>
                    <input class="input" wire:model="title" placeholder="Résumé du problème" required>
                    @error('title') <span class="text-error" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>
                <div class="form-group" style="grid-column:1/-1;">
                    <label class="field-label">Description *</label>
                    <textarea class="input" wire:model="description" rows="6" placeholder="Décrivez le problème, les étapes pour le reproduire, l'impact…" required></textarea>
                    @error('description') <span class="text-error" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>
                <div class="form-group">
                    <label class="field-label">Catégorie</label>
                    <input class="input" wire:model="category" placeholder="Ex. Stock, Caisse, Facturation…">
                </div>
                <div class="form-group">
                    <label class="field-label">Priorité *</label>
                    <select class="input" wire:model="priority" required>
                        @foreach ($priorities as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="form-group">
                    <label class="field-label">Assigner à</label>
                    <select class="input" wire:model="assigned_to">
                        <option value="">— Non assigné —</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
        </section>

        <div class="page-actions" style="margin-top:20px;">
            <a class="btn btn-secondary" href="{{ route('tenant.tickets.index', ['tenant' => $tenantCode]) }}">Annuler</a>
            <button type="submit" class="btn btn-primary">Créer le ticket</button>
        </div>
    </form>
</div>
