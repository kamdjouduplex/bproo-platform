<div class="page-body cc-prospect">
    <section class="cc-card" style="margin-bottom:14px;">
        <div class="cc-card__head">
            <div>
                <h2 class="cc-card__title" style="margin:0;">
                    {{ $prospectId ? ($company_name ?: 'Fiche prospect') : 'Nouveau prospect' }}
                </h2>
                @if ($prospectId)
                    <div style="display:flex;flex-wrap:wrap;gap:6px;margin-top:8px;">
                        <span class="badge badge-secondary">{{ $stages[$stage] ?? $stage }}</span>
                        <span class="badge badge-secondary">{{ $productTypes[$product_interest]['label'] ?? $product_interest }}</span>
                        @if ($city || $country)
                            <span class="badge badge-secondary">{{ trim(($city ?: '').($city && $country ? ', ' : '').($country ?: '')) }}</span>
                        @endif
                    </div>
                @endif
            </div>
            <div style="display:flex;gap:8px;flex-wrap:wrap;">
                <a class="btn btn-secondary btn-sm" href="{{ route('system.prospects') }}">Liste</a>
                @if ($prospectId && !$prospect?->converted_tenant_id)
                    @if ($stage === 'lead')
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="promoteToOpportunity">→ Opportunité</button>
                    @endif
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="markLost" wire:confirm="Marquer perdu ?">Perdu</button>
                    @if ($stage !== 'won')
                        <button type="button" class="btn btn-secondary btn-sm" wire:click="markWon">Gagné</button>
                    @elseif ($canConvert)
                        <a class="btn btn-primary btn-sm" href="{{ route('system.tenants.create', ['prospect' => $prospectId]) }}">Convertir</a>
                    @endif
                @endif
                <button type="button" class="btn btn-primary btn-sm" wire:click="save">Enregistrer</button>
            </div>
        </div>

        <div class="cc-card__body">
            @if ($errors->any())
                <div style="margin-bottom:14px;background:#fef2f2;border:1px solid #fecaca;color:#b91c1c;padding:10px 14px;border-radius:8px;">
                    <ul style="margin:0;padding-left:18px;">
                        @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                    </ul>
                </div>
            @endif

            @if ($prospect?->convertedTenant)
                <div style="margin-bottom:16px;padding:10px 14px;border-radius:10px;background:#f0fdf4;border:1px solid #bbf7d0;font-size:13px;">
                    Client :
                    <a href="{{ route('system.tenants.show', $prospect->convertedTenant) }}">
                        {{ $prospect->convertedTenant->name }} ({{ $prospect->convertedTenant->code }})
                    </a>
                </div>
            @endif

            <h3 class="cc-prospect__section">Entreprise</h3>
            <div class="form-grid" style="margin-bottom:18px;">
                <div class="field" style="grid-column:1/-1;">
                    <label class="field-label">Nom</label>
                    <input class="input" wire:model="company_name" placeholder="Nom de la société">
                </div>
                <div class="field">
                    <label class="field-label">Application</label>
                    <select class="input" wire:model="product_interest">
                        @foreach ($productTypes as $key => $cfg)
                            <option value="{{ $key }}">{{ $cfg['label'] ?? $key }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Source</label>
                    <select class="input" wire:model="source">
                        @foreach ($sources as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Pays</label>
                    <input class="input" wire:model="country">
                </div>
                <div class="field">
                    <label class="field-label">Ville</label>
                    <input class="input" wire:model="city">
                </div>
            </div>

            <h3 class="cc-prospect__section">Contact</h3>
            <div class="form-grid" style="margin-bottom:18px;">
                <div class="field">
                    <label class="field-label">Nom</label>
                    <input class="input" wire:model="contact_name" placeholder="Décideur / interlocuteur">
                </div>
                <div class="field">
                    <label class="field-label">Téléphone</label>
                    <input class="input" wire:model="contact_phone" placeholder="+224 …">
                </div>
                <div class="field">
                    <label class="field-label">Email</label>
                    <input class="input" wire:model="contact_email" placeholder="contact@entreprise.com">
                </div>
            </div>

            <h3 class="cc-prospect__section">Pipeline</h3>
            <div class="form-grid" style="margin-bottom:18px;">
                <div class="field">
                    <label class="field-label">Étape</label>
                    <select class="input" wire:model.live="stage" @if($prospect?->converted_tenant_id) disabled @endif>
                        @foreach ($stages as $key => $label)
                            <option value="{{ $key }}">{{ $label }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Commercial</label>
                    <select class="input" wire:model="owner_user_id">
                        <option value="">— Non affecté —</option>
                        @foreach ($salespeople as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="field">
                    <label class="field-label">Prochain suivi</label>
                    <input class="input" type="date" wire:model="next_follow_up_at">
                </div>
                <div class="field">
                    <label class="field-label">Valeur estimée</label>
                    <input class="input" type="number" min="0" step="1000" wire:model="expected_value">
                </div>
                <div class="field">
                    <label class="field-label">Probabilité %</label>
                    <input class="input" type="number" min="0" max="100" wire:model="probability">
                </div>
            </div>

            <h3 class="cc-prospect__section">Notes / stratégie</h3>
            <div class="field">
                <textarea class="input cc-prospect__notes" rows="12" wire:model="notes" placeholder="Angle d’approche, objections, contexte…"></textarea>
            </div>
        </div>
    </section>

    @if ($prospect)
        <section class="cc-card" style="margin-bottom:14px;">
            <div class="cc-card__head">
                <h2 class="cc-card__title">Activités</h2>
            </div>
            <div class="cc-card__body">
                <div class="form-grid" style="margin-bottom:12px;">
                    <div class="field">
                        <label class="field-label">Type</label>
                        <select class="input" wire:model="activity_type">
                            @foreach ($activityTypes as $key => $label)
                                @if (in_array($key, ['note','call','email','meeting','follow_up'], true))
                                    <option value="{{ $key }}">{{ $label }}</option>
                                @endif
                            @endforeach
                        </select>
                    </div>
                    <div class="field" style="grid-column:1/-1;">
                        <label class="field-label">Compte-rendu</label>
                        <textarea class="input" rows="3" placeholder="Détail de l’échange…" wire:model="activity_body"></textarea>
                    </div>
                </div>
                <button type="button" class="btn btn-secondary btn-sm" wire:click="addNote">Ajouter</button>

                <div class="cc-prospect__timeline">
                    @forelse ($prospect->activities as $activity)
                        <article class="cc-prospect__timeline-item">
                            <div class="cc-prospect__timeline-meta">
                                {{ $activity->created_at->format('d/m/Y H:i') }}
                                · {{ $activityTypes[$activity->type] ?? $activity->type }}
                                · {{ $activity->user?->name ?? 'Système' }}
                            </div>
                            @if ($activity->subject)
                                <div class="cc-prospect__timeline-subject">{{ $activity->subject }}</div>
                            @endif
                            @if ($activity->body)
                                <div class="cc-prospect__timeline-body">{{ $activity->body }}</div>
                            @endif
                        </article>
                    @empty
                        <p style="color:#94a3b8;margin:12px 0 0;">Aucune activité.</p>
                    @endforelse
                </div>
            </div>
        </section>

        @if ($canConvert)
            <section class="cc-card" style="border-color:#86efac;background:#f0fdf4;">
                <div class="cc-card__head" style="border-color:#bbf7d0;">
                    <h2 class="cc-card__title">Convertir en client</h2>
                </div>
                <div class="cc-card__body" style="display:flex;flex-wrap:wrap;gap:12px;align-items:center;justify-content:space-between;">
                    <p style="margin:0;font-size:13px;color:#166534;max-width:42rem;">
                        Les coordonnées du prospect seront reportées sur le formulaire de création client.
                        Vous pourrez compléter le code, l’admin et créer l’entreprise.
                    </p>
                    <a class="btn btn-primary" href="{{ route('system.tenants.create', ['prospect' => $prospectId]) }}">
                        Ouvrir la création client
                    </a>
                </div>
            </section>
        @endif
    @endif
</div>
