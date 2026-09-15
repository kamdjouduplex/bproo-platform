<div class="page-body">
    <p style="margin:0 0 16px;color:#64748b;max-width:48rem;">
        Journal des batches OUT reçus des installs desktop. Stockage append-only
        (backup) — aucun replay dans les bases SaaS pour l’instant.
        API : <code>POST /api/sync/out</code>
    </p>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Batches récents ({{ $batches->count() }})</div>
            <select class="form-control" style="width:auto;" wire:model.live="filter_product">
                <option value="">Tous produits</option>
                <option value="pharma">pharma</option>
                <option value="erp">erp</option>
                <option value="pressing">pressing</option>
                <option value="school">school</option>
                <option value="bat">bat</option>
            </select>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Reçu</th>
                        <th>Entreprise</th>
                        <th>Install</th>
                        <th>Produit</th>
                        <th>Batch</th>
                        <th>Statut</th>
                        <th>Acceptés</th>
                        <th>Doublons</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($batches as $batch)
                        <tr>
                            <td>{{ $batch->received_at?->format('d/m/Y H:i') }}</td>
                            <td>
                                @if ($batch->tenant)
                                    <a href="{{ route('system.tenants.desktop-installs', $batch->tenant) }}">{{ $batch->tenant->code }}</a>
                                @else
                                    —
                                @endif
                            </td>
                            <td style="font-size:11px;"><code>{{ \Illuminate\Support\Str::limit($batch->install?->uuid, 13, '…') }}</code></td>
                            <td><code>{{ $batch->product_key }}</code></td>
                            <td style="font-size:11px;"><code>{{ \Illuminate\Support\Str::limit($batch->uuid, 13, '…') }}</code></td>
                            <td>
                                @if ($batch->status === 'accepted')
                                    <span class="badge badge-success">accepted</span>
                                @elseif ($batch->status === 'rejected')
                                    <span class="badge badge-danger">rejected</span>
                                @else
                                    <span class="badge badge-secondary">{{ $batch->status }}</span>
                                @endif
                            </td>
                            <td>{{ $batch->accepted_count }}/{{ $batch->event_count }}</td>
                            <td>{{ $batch->duplicate_count }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Aucun batch sync reçu pour l’instant.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
