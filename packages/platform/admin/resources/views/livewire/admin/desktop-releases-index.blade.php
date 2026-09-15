<div class="page-body">
    <p style="margin:0 0 16px;color:#64748b;max-width:48rem;">
        Catalogue de packages desktop (hors SaaS). Le client vérifie via
        <code>POST /api/updates/check</code> avec un jeton licence Phase 1,
        puis applique localement (healthcheck + rollback).
    </p>

    <section class="card" style="padding:16px;margin-bottom:16px;">
        <div class="table-title" style="margin-bottom:12px;">Nouvelle release (brouillon)</div>
        <form wire:submit.prevent="createDraft" class="form-grid">
            <div class="field">
                <label class="field-label" for="product_key">Produit</label>
                <select id="product_key" class="form-control" wire:model="product_key">
                    <option value="pharma">pharma</option>
                    <option value="erp">erp</option>
                    <option value="pressing">pressing</option>
                    <option value="school">school</option>
                    <option value="bat">bat</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="channel">Canal</label>
                <select id="channel" class="form-control" wire:model="channel">
                    <option value="stable">stable</option>
                    <option value="beta">beta</option>
                </select>
            </div>
            <div class="field">
                <label class="field-label" for="version">Version (semver)</label>
                <input id="version" type="text" class="form-control" wire:model="version" placeholder="1.0.0" required>
            </div>
            <div class="field">
                <label class="field-label" for="min_version">Version mini client</label>
                <input id="min_version" type="text" class="form-control" wire:model="min_version" placeholder="optionnel">
            </div>
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label" for="changelog">Changelog</label>
                <textarea id="changelog" class="form-control" rows="3" wire:model="changelog"></textarea>
            </div>
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label" for="package_url">URL package (CDN)</label>
                <input id="package_url" type="url" class="form-control" wire:model="package_url" placeholder="https://...">
            </div>
            <div class="field">
                <label class="field-label" for="package_sha256">SHA-256 (si URL)</label>
                <input id="package_sha256" type="text" class="form-control" wire:model="package_sha256" placeholder="64 hex">
            </div>
            <div class="field">
                <label class="field-label" for="package_file">Ou fichier local</label>
                <input id="package_file" type="file" class="form-control" wire:model="package_file">
                @if ($package_file)
                    <div style="font-size:12px;color:#64748b;margin-top:4px;">Upload… {{ $package_file->getClientOriginalName() }}</div>
                @endif
            </div>
            <div class="field" style="align-self:end;">
                <label style="display:flex;gap:8px;align-items:center;">
                    <input type="checkbox" wire:model="mandatory"> Mise à jour obligatoire
                </label>
            </div>
            <div class="field" style="align-self:end;">
                <button type="submit" class="btn btn-primary" wire:loading.attr="disabled">Créer le brouillon</button>
            </div>
        </form>
    </section>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Releases ({{ $releases->count() }})</div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <select class="form-control" style="width:auto;" wire:model.live="filter_product">
                    <option value="">Tous produits</option>
                    <option value="pharma">pharma</option>
                    <option value="erp">erp</option>
                    <option value="pressing">pressing</option>
                    <option value="school">school</option>
                    <option value="bat">bat</option>
                </select>
                <select class="form-control" style="width:auto;" wire:model.live="filter_channel">
                    <option value="">Tous canaux</option>
                    <option value="stable">stable</option>
                    <option value="beta">beta</option>
                </select>
                <select class="form-control" style="width:auto;" wire:model.live="filter_status">
                    <option value="">Tous statuts</option>
                    <option value="draft">draft</option>
                    <option value="published">published</option>
                    <option value="yanked">yanked</option>
                </select>
            </div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Produit</th>
                        <th>Canal</th>
                        <th>Version</th>
                        <th>Statut</th>
                        <th>SHA-256</th>
                        <th>Taille</th>
                        <th>Publiée</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($releases as $release)
                        <tr>
                            <td><code>{{ $release->product_key }}</code></td>
                            <td>{{ $release->channel }}</td>
                            <td>
                                <strong>{{ $release->version }}</strong>
                                @if ($release->mandatory)
                                    <span class="badge badge-secondary">mandatory</span>
                                @endif
                            </td>
                            <td>
                                @if ($release->status === 'published')
                                    <span class="badge badge-success">published</span>
                                @elseif ($release->status === 'yanked')
                                    <span class="badge badge-danger">yanked</span>
                                @else
                                    <span class="badge badge-secondary">draft</span>
                                @endif
                            </td>
                            <td style="font-size:11px;">
                                @if ($release->package_sha256)
                                    <code title="{{ $release->package_sha256 }}">{{ \Illuminate\Support\Str::limit($release->package_sha256, 12, '…') }}</code>
                                @else
                                    —
                                @endif
                            </td>
                            <td>
                                @if ($release->package_size)
                                    {{ number_format($release->package_size / 1048576, 1, ',', ' ') }} Mo
                                @else
                                    —
                                @endif
                            </td>
                            <td>{{ $release->published_at?->format('d/m/Y H:i') ?: '—' }}</td>
                            <td style="white-space:nowrap;">
                                @if ($release->status === 'draft')
                                    <button type="button" class="btn btn-primary btn-sm" wire:click="publish({{ $release->id }})" wire:confirm="Publier {{ $release->version }} ?">Publier</button>
                                @endif
                                @if ($release->status === 'published')
                                    <button type="button" class="btn btn-secondary btn-sm" style="color:#b91c1c;" wire:click="yank({{ $release->id }})" wire:confirm="Retirer cette release ?">Yank</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="8">Aucune release desktop.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
