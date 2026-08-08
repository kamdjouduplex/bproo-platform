@php
    $tenantCode = request()->query('tenant')
        ?? session('tenant_code')
        ?? optional(request()->attributes->get('tenant'))->code;
    $colCount = count($visibleColumns) + 1;
@endphp

<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">{{ $catalogNoun['title'] ?? 'Catalogue' }}</div>
            <div style="display:flex; gap:8px; align-items:center; flex-wrap:wrap;">
                <form wire:submit.prevent="applySearch" style="display: inline-flex; gap: 4px;">
                    <input class="input input-sm" type="text" wire:model="search" placeholder="Désignation, référence ou code-barres" style="min-width: 220px;" aria-label="Rechercher">
                    <button type="submit" class="btn btn-secondary btn-sm">Rechercher</button>
                </form>
                <select class="input input-sm" wire:model="perPage">
                    <option value="10">10</option>
                    <option value="25">25</option>
                    <option value="50">50</option>
                </select>
                @if ($canConfigureList)
                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.items.list-config', ['tenant' => $tenantCode]) }}">Config</a>
                @endif
                @if ($canCreate)
                    <a class="btn btn-primary btn-sm" href="{{ route('tenant.items.create', ['tenant' => $tenantCode]) }}">Nouveau</a>
                @endif
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        @foreach ($visibleColumns as $col)
                            <th>{{ $col['label'] }}</th>
                        @endforeach
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($items as $item)
                        <tr>
                            @foreach ($visibleColumns as $col)
                                <td>
                                    @switch($col['key'])
                                        @case('reference')
                                            <strong>{{ $item->sku ?? '—' }}</strong>
                                            @break
                                        @case('designation')
                                            {{ $item->name }}
                                            @if (!empty(($item->metadata ?? [])['is_set']))
                                                <span class="badge" style="margin-left:6px;background:#eef2ff;color:#4338ca;">Lot</span>
                                            @endif
                                            @break
                                        @case('category')
                                            {{ $item->category?->name ?? '—' }}
                                            @break
                                        @case('brand')
                                            {{ $item->brand?->name ?? '—' }}
                                            @break
                                        @case('unit')
                                            {{ $item->unit?->abbreviation ?? $item->unit?->name ?? '—' }}
                                            @break
                                        @case('price')
                                            @if ($item->unitPrices->isEmpty())
                                                {{ fmt_money($item->price) }}
                                            @else
                                                @foreach ($item->unitPrices as $p)
                                                    <span style="display: block; font-size: 12px;">{{ fmt_money($p->price) }} / {{ $p->unit->abbreviation ?? $p->unit->name }}</span>
                                                @endforeach
                                            @endif
                                            @break
                                        @case('cost')
                                            {{ fmt_money($item->cost) }}
                                            @break
                                        @case('barcode')
                                            {{ $item->barcode ?? '—' }}
                                            @break
                                        @case('status')
                                            @if ($item->is_active)
                                                <span class="badge badge-success">Actif</span>
                                            @else
                                                <span class="badge badge-warning">Inactif</span>
                                            @endif
                                            @break
                                        @default
                                            —
                                    @endswitch
                                </td>
                            @endforeach
                            <td style="display:flex; gap:4px; flex-wrap:wrap;">
                                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.items.show', [$item->id, 'tenant' => $tenantCode]) }}">Voir</a>
                                @if ($canUpdate)
                                    <a class="btn btn-secondary btn-sm" href="{{ route('tenant.items.edit', [$item->id, 'tenant' => $tenantCode]) }}">Modifier</a>
                                @endif
                                @if ($canDelete)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="delete({{ $item->id }})" onclick="return confirm('Supprimer ce {{ $catalogNoun['singular'] ?? 'article' }} ?')">Supprimer</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                    @if ($items->count() === 0)
                        <tr>
                            <td colspan="{{ $colCount }}">Aucun {{ $catalogNoun['singular'] ?? 'article' }} pour le moment.</td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>
        @if ($items->hasPages())
            <div class="table-pagination">
                {{ $items->appends(['tenant' => $tenantCode])->links('livewire.inovcom') }}
            </div>
        @endif
    </section>
</div>
