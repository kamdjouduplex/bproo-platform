@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
@endphp

<div class="page-body">
    <section class="card">
        <h2 class="card-title">Configuration de la liste</h2>
        <p style="color:#6b7280; font-size:13px; margin-bottom:16px;">
            Cochez les colonnes à afficher et utilisez les flèches pour définir l'ordre. La colonne « Coût » n'apparaît que pour les utilisateurs ayant la permission « Voir le coût d'achat ».
        </p>

        <div style="display:flex; flex-direction:column; gap:8px;">
            @foreach ($columns as $index => $col)
                <div style="display:flex; align-items:center; gap:12px; padding:10px 12px; border:1px solid #e5e7eb; border-radius:8px; background:#fafafa;">
                    <label class="field-toggle" style="flex:1; margin:0;">
                        <input type="checkbox" wire:model="columns.{{ $index }}.visible">
                        <strong>{{ $col['label'] }}</strong>
                        @if (($col['requires_permission'] ?? null) === 'items.view_cost')
                            <span style="font-size:11px; color:#6b7280; margin-left:6px;">(permission coût requise)</span>
                        @endif
                    </label>
                    <div style="display:flex; gap:4px;">
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="moveUp({{ $index }})" @if($index === 0) disabled @endif title="Monter">↑</button>
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="moveDown({{ $index }})" @if($index === count($columns) - 1) disabled @endif title="Descendre">↓</button>
                    </div>
                </div>
            @endforeach
        </div>

        <div class="page-actions" style="margin-top: 24px;">
            <a class="btn btn-secondary" href="{{ route('tenant.items.index', ['tenant' => $tenantCode]) }}">Annuler</a>
            <button type="button" class="btn btn-secondary" wire:click="resetDefaults">Réinitialiser</button>
            <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
        </div>
    </section>
</div>
