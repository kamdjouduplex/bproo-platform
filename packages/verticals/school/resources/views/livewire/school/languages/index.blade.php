<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Langues de l’interface</h2>
            <div class="sch-list-head__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.options.index', ['tenant' => $tenantCode]) }}">Paramétrage listes</a>
            </div>
        </div>

        <div style="padding:16px;">
            <p style="margin:0 0 14px; color:#64748b; font-size:13px;">
                Le français reste toujours disponible. Les utilisateurs choisissent leur langue dans l’en-tête (préférence enregistrée sur le compte).
            </p>

            <div class="form-grid" style="max-width:640px;">
                <div class="form-span-2">
                    <label class="label">Langues activées</label>
                    <div style="display:flex; flex-wrap:wrap; gap:10px 18px; margin-top:6px;">
                        @foreach($all as $code => $label)
                            <label style="display:inline-flex; align-items:center; gap:6px; font-size:13px;">
                                <input type="checkbox" value="{{ $code }}" wire:model="enabled" @if($code === 'fr') disabled @endif>
                                <span>{{ $label }} <span style="color:#94a3b8;">({{ strtoupper($code) }})</span></span>
                            </label>
                        @endforeach
                    </div>
                    @error('enabled') <span class="text-error">{{ $message }}</span> @enderror
                    @error('enabled.*') <span class="text-error">{{ $message }}</span> @enderror
                </div>

                <div>
                    <label class="label">Langue par défaut (établissement)</label>
                    <select class="input" wire:model="defaultLocale">
                        @foreach($all as $code => $label)
                            <option value="{{ $code }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    @error('defaultLocale') <span class="text-error">{{ $message }}</span> @enderror
                </div>
            </div>

            <div style="margin-top:18px;">
                <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
            </div>
        </div>
    </section>
</div>
