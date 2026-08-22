<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Matricules élèves</h2>
            <div class="sch-list-head__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.options.index', ['tenant' => $tenantCode]) }}">Listes</a>
            </div>
        </div>

        <div style="padding: 8px 16px 20px; max-width: 640px;">
            <p style="color:#64748b; font-size:13px; margin:0 0 16px;">
                Le matricule est généré à la création de l’élève (identifiant interne à l’école). Le NISU, lui, est attribué par le ministère et se saisit sur la fiche élève. Les matricules déjà attribués ne sont pas renumérotés.
            </p>

            <div class="form-grid">
                <div>
                    <label class="label">Préfixe</label>
                    <input class="input" wire:model.live="prefix" type="text" placeholder="SCH">
                    @error('prefix') <span class="text-error">{{ $message }}</span> @enderror
                </div>
                <div>
                    <label class="label">Année</label>
                    <select class="input" wire:model.live="yearFormat">
                        <option value="yyyy">Année complète (2026)</option>
                        <option value="yy">Année courte (26)</option>
                        <option value="none">Sans année</option>
                    </select>
                </div>
                <div>
                    <label class="label">Séparateur</label>
                    <input class="input" wire:model.live="separator" type="text" maxlength="2">
                </div>
                <div>
                    <label class="label">Chiffres du compteur</label>
                    <select class="input" wire:model.live="seqPadding">
                        @for($i = 3; $i <= 8; $i++)
                            <option value="{{ $i }}">{{ $i }} ({{ str_pad('1', $i, '0', STR_PAD_LEFT) }})</option>
                        @endfor
                    </select>
                </div>
            </div>

            <div style="margin-top:16px; padding:12px 14px; border:1px solid #dbeafe; background:#eff6ff; border-radius:8px;">
                <div style="font-size:12px; color:#1e40af;">Aperçu</div>
                <div style="font-size:20px; font-weight:800; letter-spacing:0.04em;">{{ $preview }}</div>
                <div style="font-size:12px; color:#64748b; margin-top:4px;">Prochain disponible : <strong>{{ $next }}</strong></div>
            </div>

            @if($canManage)
                <div style="margin-top:16px;">
                    <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
                </div>
            @endif
        </div>
    </section>

    <section class="card app-table-card" style="margin-top:16px;">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Code établissement (ministère)</h2>
        </div>
        <div style="padding: 8px 16px 20px; max-width: 640px;">
            <p style="color:#64748b; font-size:13px; margin:0 0 16px;">
                L’école est enregistrée sur le site du ministère avec ce code. Il est repris dans l’export Excel des élèves (bouton « Excel ministère » sur la liste).
            </p>
            <div>
                <label class="label">Code établissement</label>
                <input class="input" wire:model="ministrySchoolCode" type="text" placeholder="Ex. 0001234">
                @error('ministrySchoolCode') <span class="text-error">{{ $message }}</span> @enderror
            </div>
            @if($canManage)
                <div style="margin-top:16px;">
                    <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
                </div>
            @endif
        </div>
    </section>
</div>
