<div class="page-body">
    @include('pressing::livewire.settings.partials.nav')

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @error('methods')
        <div class="alert alert-danger" style="margin-bottom:16px;">{{ $message }}</div>
    @enderror

    <section class="card app-table-card">
        <div class="client-list-head">
            <h2 class="client-list-head__title">Types de paiement</h2>
            <div class="client-list-head__actions">
                @if ($canManage)
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="addMethod">Ajouter</button>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="save">Enregistrer</button>
                @endif
            </div>
        </div>

        <div class="table-wrap">
            <table class="table">
                <thead>
                    <tr>
                        <th>Clé</th>
                        <th>Libellé</th>
                        <th>Actif</th>
                        <th></th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($methods as $index => $method)
                        <tr>
                            <td>
                                <input class="input input-sm" wire:model="methods.{{ $index }}.key" @disabled(! $canManage)>
                                @error('methods.'.$index.'.key')<div style="color:#dc2626;font-size:12px;">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input class="input input-sm" wire:model="methods.{{ $index }}.label" @disabled(! $canManage)>
                                @error('methods.'.$index.'.label')<div style="color:#dc2626;font-size:12px;">{{ $message }}</div>@enderror
                            </td>
                            <td>
                                <input type="checkbox" wire:model="methods.{{ $index }}.is_active" @disabled(! $canManage)>
                            </td>
                            <td>
                                @if ($canManage)
                                    <button type="button" class="btn btn-secondary btn-sm" wire:click="removeMethod({{ $index }})">Retirer</button>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </section>
</div>
