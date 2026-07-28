@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom: 16px;">
            {{ session('success') }}
        </div>
    @endif

    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom: 16px;">
            {{ session('error') }}
        </div>
    @endif

    @if (!empty($statusWarning))
        <div class="alert alert-error" style="margin-bottom: 16px;">
            {{ $statusWarning }}
        </div>
    @endif

    <form wire:submit.prevent="save">
        <section class="card">
            <h2 class="card-title">Informations de la dépense</h2>
            <div class="form-grid">
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Catégorie *</label>
                    @if ($useNewCategory)
                        <div style="display:flex; gap:8px; align-items:flex-start; flex-wrap:wrap;">
                            <input class="input"
                                   type="text"
                                   wire:model="newCategoryName"
                                   placeholder="Nom de la nouvelle catégorie"
                                   style="flex:1; min-width:200px;"
                                   autofocus>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelNewCategory">Liste existante</button>
                        </div>
                        @error('newCategoryName') <span class="text-error" style="font-size:12px;">{{ $message }}</span> @enderror
                        <p class="field-hint" style="margin-top:6px;">La catégorie sera créée à l'enregistrement si elle n'existe pas déjà.</p>
                    @else
                        <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                            <select class="input" wire:model="expense_category_id" required style="flex:1; min-width:200px;">
                                <option value="">Sélectionner une catégorie</option>
                                @foreach ($categories as $category)
                                    <option value="{{ $category->id }}">{{ $category->name }}</option>
                                @endforeach
                            </select>
                            @if ($canManageCategories)
                                <button type="button" class="btn btn-secondary btn-sm" wire:click="enableNewCategory">+ Nouvelle catégorie</button>
                            @endif
                        </div>
                        @error('expense_category_id') <span class="text-error" style="font-size:12px;">{{ $message }}</span> @enderror
                    @endif
                </div>
                <div class="field">
                    <label class="field-label">Montant *</label>
                    <input class="input" wire:model="amount" type="number" step="0.01" min="0.01" required placeholder="0.00">
                </div>
                <div class="field">
                    <label class="field-label">Date de la dépense *</label>
                    <input class="input" wire:model="expense_date" type="date" required>
                </div>
                <div class="field">
                    <label class="field-label">Méthode de paiement *</label>
                    <select class="input" wire:model="payment_method" required>
                        <option value="cash">Espèces</option>
                        <option value="check">Chèque</option>
                        <option value="bank_transfer">Virement bancaire</option>
                        <option value="mobile_money">Mobile Money</option>
                        <option value="other">Autre</option>
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Statut *</label>
                    <select class="input" wire:model="status" required>
                        <option value="pending">En attente d'approbation</option>
                        <option value="draft">Brouillon</option>
                        @if ($expenseId)
                            <option value="approved" {{ $status === 'approved' ? 'selected' : '' }}>Approuvé</option>
                            <option value="rejected" {{ $status === 'rejected' ? 'selected' : '' }}>Rejeté</option>
                            <option value="paid" {{ $status === 'paid' ? 'selected' : '' }}>Payé</option>
                        @endif
                    </select>
                </div>
                <div class="field" style="grid-column: 1 / -1;">
                    <label class="field-label">Description</label>
                    <textarea class="input" wire:model="description" rows="4" placeholder="Détails de la dépense..."></textarea>
                </div>
            </div>
        </section>

        <div class="page-actions" style="margin-top: 24px;">
            <a class="btn btn-secondary" href="{{ route('tenant.expenses.index', ['tenant' => $tenantCode]) }}">Annuler</a>
            <button type="submit" class="btn btn-primary">
                {{ $expenseId ? 'Mettre à jour' : 'Enregistrer' }}
            </button>
        </div>
    </form>
</div>
