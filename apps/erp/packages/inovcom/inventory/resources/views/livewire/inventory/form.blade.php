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

    @if (!$count || $count->isDraft())
        <form wire:submit.prevent="save">
            <section class="card">
                <h2 class="card-title">Informations de l'inventaire</h2>
                <div class="form-grid">
                    <div class="field">
                        <label class="field-label">Titre *</label>
                        <input class="input" wire:model="title" required placeholder="Ex: Inventaire mensuel - Janvier 2026">
                    </div>
                    <div class="field">
                        <label class="field-label">Description</label>
                        <textarea class="input" wire:model="description" rows="3" placeholder="Notes sur cet inventaire..."></textarea>
                    </div>
                    <div class="field">
                        <label class="field-label">
                            <input type="checkbox" wire:model="allow_operations">
                            Autoriser les opérations pendant l'inventaire
                        </label>
                        <small style="color: #6b7280; display: block; margin-top: 4px;">
                            Si activé, les ventes et achats peuvent continuer pendant l'inventaire
                        </small>
                    </div>
                </div>
            </section>

            <div class="page-actions" style="margin-top: 24px;">
                <a class="btn btn-secondary" href="{{ route('tenant.inventory.index', ['tenant' => $tenantCode]) }}">Annuler</a>
                <button type="submit" class="btn btn-primary">
                    {{ $countId ? 'Mettre à jour' : 'Créer l\'inventaire' }}
                </button>
            </div>
        </form>
    @endif

    @if ($count && ($count->isInProgress() || $count->isCompleted()))
        <div style="display: grid; grid-template-columns: 1fr 300px; gap: 24px;">
            {{-- Left: Count Lines --}}
            <div>
                <section class="card">
                    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 16px;">
                        <h2 class="card-title">Lignes de comptage</h2>
                        @if ($count->isInProgress())
                            <span class="badge badge-warning">En cours</span>
                        @else
                            <span class="badge badge-success">Terminé</span>
                        @endif
                    </div>

                    @if (!empty($lines))
                        <div class="table-scroll">
                            <table>
                                <thead>
                                    <tr>
                                        <th>Article</th>
                                        <th>Attendu</th>
                                        <th>Compté</th>
                                        <th>Différence</th>
                                        <th>Valeur diff.</th>
                                        <th>Notes</th>
                                        <th>Actions</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($lines as $index => $line)
                                        @php
                                            $diff = (float) $line['difference'];
                                            $valueDiff = (float) $line['value_difference'];
                                        @endphp
                                        <tr>
                                            <td><x-item-label :reference="$line['item_sku'] ?? null" :name="$line['item_name'] ?? null" /></td>
                                            <td>{{ fmt_num((float) $line['expected_quantity']) }} {{ $line['item_unit'] }}</td>
                                            <td>
                                                @if ($count->isInProgress())
                                                    <input type="number" 
                                                           class="input input-sm" 
                                                           wire:model.debounce.500ms="lines.{{ $index }}.counted_quantity"
                                                           min="0" 
                                                           step="0.001" 
                                                           style="width: 100px;"
                                                           placeholder="0">
                                                @else
                                                    {{ $line['counted_quantity'] ? fmt_num((float) $line['counted_quantity']) . ' ' . $line['item_unit'] : '-' }}
                                                @endif
                                            </td>
                                            <td>
                                                @if ($diff != 0)
                                                    <span style="color: {{ $diff > 0 ? '#16a34a' : '#dc2626' }};">
                                                        {{ $diff > 0 ? '+' : '' }}{{ fmt_num($diff) }}
                                                    </span>
                                                @else
                                                    <span style="color: #6b7280;">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($valueDiff != 0)
                                                    <span style="color: {{ $valueDiff > 0 ? '#16a34a' : '#dc2626' }};">
                                                        {{ fmt_money($valueDiff) }} FCFA
                                                    </span>
                                                @else
                                                    <span style="color: #6b7280;">-</span>
                                                @endif
                                            </td>
                                            <td>
                                                @if ($count->isInProgress())
                                                    <input type="text" 
                                                           class="input input-sm" 
                                                           wire:model.debounce.500ms="lines.{{ $index }}.notes"
                                                           placeholder="Notes..."
                                                           style="width: 150px;">
                                                @else
                                                    {{ $line['notes'] ?: '-' }}
                                                @endif
                                            </td>
                                            <td>
                                                @if ($count->isInProgress())
                                                    <button type="button" 
                                                            class="btn btn-secondary btn-sm" 
                                                            wire:click="updateLine({{ $index }})">
                                                        Enregistrer
                                                    </button>
                                                @endif
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        </div>
                    @else
                        <p style="text-align: center; padding: 40px; color: #999;">Aucune ligne de comptage</p>
                    @endif
                </section>
            </div>

            {{-- Right: Summary and Actions --}}
            <div>
                <section class="card">
                    <h2 class="card-title">Résumé</h2>
                    <div style="padding: 16px 0;">
                        <div style="margin-bottom: 16px;">
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Référence</div>
                            <div style="font-weight: 600;">{{ $count->reference }}</div>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Progression</div>
                            <div style="display: flex; align-items: center; gap: 8px;">
                                <div style="flex: 1; height: 12px; background: #e5e7eb; border-radius: 6px; overflow: hidden;">
                                    <div style="height: 100%; width: {{ $count->progress_percentage ?? 0 }}%; background: {{ ($count->progress_percentage ?? 0) === 100 ? '#16a34a' : '#3b82f6' }}; transition: width 0.3s;"></div>
                                </div>
                                <span style="font-size: 14px; font-weight: 600;">{{ fmt_num($count->progress_percentage ?? 0, 1) }}%</span>
                            </div>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Total attendu</div>
                            <div style="font-weight: 600;">{{ fmt_num($count->total_expected_quantity ?? 0) }}</div>
                        </div>
                        <div style="margin-bottom: 16px;">
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Total compté</div>
                            <div style="font-weight: 600;">{{ fmt_num($count->total_counted_quantity ?? 0) }}</div>
                        </div>
                        <div style="margin-bottom: 16px; padding-top: 16px; border-top: 1px solid #e5e7eb;">
                            <div style="font-size: 12px; color: #6b7280; margin-bottom: 4px;">Différence totale</div>
                            <div style="font-size: 18px; font-weight: 600; color: {{ ($count->total_difference ?? 0) > 0 ? '#16a34a' : (($count->total_difference ?? 0) < 0 ? '#dc2626' : '#6b7280') }};">
                                {{ ($count->total_difference ?? 0) > 0 ? '+' : '' }}{{ fmt_num($count->total_difference ?? 0) }}
                            </div>
                            <div style="font-size: 14px; color: #6b7280; margin-top: 4px;">
                                {{ fmt_money($count->total_value_difference ?? 0) }} FCFA
                            </div>
                        </div>
                    </div>
                </section>

                @if ($count->isInProgress())
                    <section class="card" style="margin-top: 16px;">
                        <h2 class="card-title">Actions</h2>
                        <div style="display: flex; flex-direction: column; gap: 8px;">
                            <button type="button" 
                                    class="btn btn-primary" 
                                    wire:click="completeCount(true)"
                                    onclick="return confirm('Finaliser l\'inventaire et appliquer les ajustements de stock ?')">
                                Finaliser et appliquer
                            </button>
                            <button type="button" 
                                    class="btn btn-secondary" 
                                    wire:click="completeCount(false)"
                                    onclick="return confirm('Finaliser l\'inventaire sans appliquer les ajustements ?')">
                                Finaliser sans appliquer
                            </button>
                        </div>
                    </section>
                @endif
            </div>
        </div>
    @endif

    @if ($count && $count->isDraft())
        <section class="card" style="margin-top: 24px;">
            <h2 class="card-title">Actions</h2>
            <button type="button" 
                    class="btn btn-primary" 
                    wire:click="startCount"
                    onclick="return confirm('Démarrer l\'inventaire ? Cela va créer une ligne pour chaque article actif.')">
                Démarrer l'inventaire
            </button>
        </section>
    @endif
</div>
