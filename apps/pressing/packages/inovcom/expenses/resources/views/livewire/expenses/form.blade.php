@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif
    @if (!empty($statusWarning))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ $statusWarning }}</div>
    @endif

    <form wire:submit.prevent="save">
        <section class="card" style="padding:16px;">
            <div class="client-list-head" style="margin-bottom:16px;">
                <h2 class="client-list-head__title">{{ $expenseId ? 'Modifier la dépense' : 'Nouvelle dépense' }}</h2>
                <div class="client-list-head__actions">
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.expenses.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                </div>
            </div>

            @if ($expenseId && $expenseReference)
                <div style="display:flex;flex-wrap:wrap;gap:8px 16px;padding:10px 12px;margin-bottom:16px;border:1px solid #e2e8f0;border-radius:10px;background:#f8fafc;font-size:13px;color:#475569;">
                    <span><strong style="color:#0f172a;">Réf.</strong> {{ $expenseReference }}</span>
                    @if ($expenseCreator)
                        <span><strong style="color:#0f172a;">Créée par</strong> {{ $expenseCreator }}</span>
                    @endif
                </div>
            @endif

            <div class="form-grid">
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Catégorie *</label>
                    @if ($useNewCategory)
                        <div style="display:flex;gap:8px;align-items:flex-start;flex-wrap:wrap;">
                            <input class="input"
                                   type="text"
                                   wire:model="newCategoryName"
                                   placeholder="Nom de la nouvelle catégorie"
                                   style="flex:1;min-width:200px;"
                                   autofocus>
                            <button type="button" class="btn btn-secondary btn-sm" wire:click="cancelNewCategory">Liste existante</button>
                        </div>
                        @error('newCategoryName') <span class="text-error" style="font-size:12px;">{{ $message }}</span> @enderror
                        <p class="field-hint" style="margin-top:6px;">La catégorie sera créée à l’enregistrement si elle n’existe pas déjà.</p>
                    @else
                        <div style="display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                            <select class="input" wire:model="expense_category_id" required style="flex:1;min-width:200px;">
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
                    <input class="input" wire:model="amount" type="number" step="0.01" min="0.01" required placeholder="0">
                    @error('amount') <span class="text-error" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label class="field-label">Date de la dépense *</label>
                    <input class="input" wire:model="expense_date" type="date" required>
                    @error('expense_date') <span class="text-error" style="font-size:12px;">{{ $message }}</span> @enderror
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
                    @error('payment_method') <span class="text-error" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>

                <div class="field">
                    <label class="field-label">Statut *</label>
                    @if ($statusLocked)
                        @php
                            $statusDisplay = [
                                'approved' => 'Approuvé',
                                'rejected' => 'Rejeté',
                                'paid' => 'Payé',
                            ][$status] ?? $status;
                        @endphp
                        <input type="hidden" wire:model="status">
                        <div class="input" style="background:#f8fafc;color:#334155;display:flex;align-items:center;">{{ $statusDisplay }}</div>
                        <p class="field-hint" style="margin-top:6px;">Le statut ne peut plus être modifié ici. Utilisez les actions d’approbation / paiement depuis la liste.</p>
                    @else
                        <select class="input" wire:model="status" required>
                            <option value="pending">En attente d’approbation</option>
                            <option value="draft">Brouillon</option>
                        </select>
                    @endif
                    @error('status') <span class="text-error" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>

                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Description</label>
                    <textarea class="input" wire:model="description" rows="4" placeholder="Motif, fournisseur, numéro de facture…"></textarea>
                    @error('description') <span class="text-error" style="font-size:12px;">{{ $message }}</span> @enderror
                </div>
            </div>
        </section>

        <div class="page-actions" style="margin-top:20px;display:flex;gap:8px;justify-content:flex-end;">
            <a class="btn btn-secondary" href="{{ route('tenant.expenses.index', ['tenant' => $tenantCode]) }}">Annuler</a>
            <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">
                <span wire:loading.remove wire:target="save">{{ $expenseId ? 'Mettre à jour' : 'Enregistrer' }}</span>
                <span wire:loading wire:target="save">Enregistrement…</span>
            </button>
        </div>
    </form>
</div>
