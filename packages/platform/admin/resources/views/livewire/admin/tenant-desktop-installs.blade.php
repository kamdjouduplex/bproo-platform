<div class="page-body">
    <div class="cc-billing__context" style="margin-bottom:16px;">
        <div class="cc-billing__context-main">
            <div class="cc-billing__company">
                <span class="cc-billing__code">{{ $tenant->code }}</span>
                <strong>{{ $tenant->name }}</strong>
            </div>
            <p style="margin:8px 0 0;color:#64748b;max-width:42rem;">
                Licences desktop / offline — indépendantes de la facturation SaaS.
                Un abonnement SaaS actif reste requis pour activer ou renouveler le heartbeat.
                Grâce hors-ligne : {{ $graceDays }} jours après le dernier contact.
            </p>
            <div class="cc-billing__links" style="margin-top:10px;">
                <a href="{{ route('system.tenants.show', $tenant) }}">← Fiche entreprise</a>
                <a href="{{ route('system.tenants.subscription', $tenant) }}">Facturation SaaS</a>
            </div>
        </div>
    </div>

    <section class="card" style="padding:16px;margin-bottom:16px;">
        <div class="table-title" style="margin-bottom:12px;">Émettre un code d’activation</div>
        <form wire:submit.prevent="issueInstall" class="form-grid">
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
                <label class="field-label" for="label">Libellé (optionnel)</label>
                <input id="label" type="text" class="form-control" wire:model="label" placeholder="Ex. Pharmacie Rue X — PC caisse">
            </div>
            <div class="field" style="align-self:end;">
                <button type="submit" class="btn btn-primary">Générer le code</button>
            </div>
        </form>
    </section>

    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Installations ({{ $installs->count() }})</div>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Code</th>
                        <th>Libellé</th>
                        <th>Produit</th>
                        <th>Statut</th>
                        <th>Activée</th>
                        <th>Dernier heartbeat</th>
                        <th>Accès offline jusqu’au</th>
                        <th>Version</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($installs as $install)
                        @php
                            $offlineUntil = $install->offlineAccessExpiresAt($graceDays);
                        @endphp
                        <tr>
                            <td><code style="user-select:all;">{{ $install->activation_code }}</code></td>
                            <td>{{ $install->label ?: '—' }}</td>
                            <td><code>{{ $install->product_key }}</code></td>
                            <td>
                                @if ($install->status === 'active')
                                    <span class="badge badge-success">active</span>
                                @elseif ($install->status === 'revoked')
                                    <span class="badge badge-danger">révoquée</span>
                                @else
                                    <span class="badge badge-secondary">en attente</span>
                                @endif
                            </td>
                            <td>{{ $install->activated_at?->format('d/m/Y H:i') ?: '—' }}</td>
                            <td>{{ $install->last_heartbeat_at?->format('d/m/Y H:i') ?: '—' }}</td>
                            <td>{{ $offlineUntil?->format('d/m/Y') ?: '—' }}</td>
                            <td>{{ $install->app_version ?: '—' }}</td>
                            <td style="white-space:nowrap;">
                                @if (! $install->isRevoked() && ! ($install->isActive() && $install->fingerprint_hash))
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm"
                                        wire:click="rotateCode({{ $install->id }})"
                                    >Régénérer code</button>
                                @endif
                                @if (! $install->isRevoked())
                                    <button
                                        type="button"
                                        class="btn btn-secondary btn-sm"
                                        style="color:#b91c1c;"
                                        wire:click="revokeInstall({{ $install->id }})"
                                        wire:confirm="Révoquer cette installation ? Le jeton desktop sera invalidé au prochain heartbeat."
                                    >Révoquer</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="9">Aucune installation desktop pour cette entreprise.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
