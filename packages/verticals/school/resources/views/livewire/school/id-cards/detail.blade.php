<div class="page-body sch-detail-page">
    @include('school::livewire.partials.detail-styles')
    <section class="card app-table-card">
        <div class="sch-detail-toolbar">
            <div><h2 class="sch-detail-toolbar__title">Carte ID #{{ $card->id }}</h2><p class="sch-detail-toolbar__hint">{{ $isManage ? 'Page de gestion' : 'Page de consultation' }}</p></div>
            <div class="sch-detail-toolbar__actions">
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.id_cards.index', ['tenant' => $tenantCode]) }}">Retour à la liste</a>
                <a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.id_cards.print', ['tenant' => $tenantCode, 'id' => $card->id]) }}" onclick="return schoolOpenPrint(this.href)">Imprimer</a>
                @if($isManage)<a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.id_cards.show', ['tenant' => $tenantCode, 'id' => $card->id]) }}">Voir</a><button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button>
                @else<a class="btn btn-primary btn-sm" href="{{ route('tenant.school.id_cards.manage', ['tenant' => $tenantCode, 'id' => $card->id]) }}">Gérer</a>@endif
            </div>
        </div>
        <div class="sch-info-grid">
            @foreach(['Élève' => ($card->student?->student_code.' — '.$card->student?->full_name), 'Année académique' => $card->academicYear?->name ?? '—', 'Lot' => $card->batch_code ?? '—', 'Code-barres' => $card->barcode_data ?? '—', 'Jeton QR' => $card->qr_token ?? '—', 'QR généré' => filled($card->qr_svg) ? 'Oui' : 'Non', 'Générée le' => $card->generated_at?->format('d/m/Y H:i') ?? '—', 'Créée le' => $card->created_at?->format('d/m/Y H:i') ?? '—', 'Mise à jour' => $card->updated_at?->format('d/m/Y H:i') ?? '—'] as $label => $value)
                <div class="sch-info-item"><span class="sch-info-item__label">{{ $label }}</span><div class="sch-info-item__value">{{ $value }}</div></div>
            @endforeach
            @if($card->qr_svg)
                <div class="sch-info-item"><span class="sch-info-item__label">Aperçu QR</span><div class="sch-info-item__value">{!! $card->qr_svg !!}</div></div>
            @endif
        </div>
        @if($isManage)<div class="sch-actions-panel"><div class="sch-actions-panel__label">Actions</div><button class="btn btn-primary btn-sm" wire:click="edit">Modifier</button><a class="btn btn-secondary btn-sm" href="{{ route('tenant.school.id_cards.print', ['tenant' => $tenantCode, 'id' => $card->id]) }}" onclick="return schoolOpenPrint(this.href)">Imprimer</a></div>@endif
    </section>
    @if($showForm)
        <div class="sch-modal-backdrop" wire:click.self="cancel"><div class="sch-modal"><div class="sch-modal__head"><h3 class="sch-modal__title">Modifier la carte ID</h3><button class="sch-modal__close" wire:click="cancel">&times;</button></div>
            <div class="sch-modal__body"><div class="form-grid">
                <div class="form-span-2">@include('school::livewire.partials.searchable-student')</div>
                <div><label class="label">Année académique</label><select class="input" wire:model="academicYearId"><option value="">—</option>@foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach</select></div>
                <div><label class="label">Code lot</label><input class="input" wire:model="batchCode"></div>
            </div></div><div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="cancel">Annuler</button><button class="btn btn-primary" wire:click="save">Enregistrer</button></div>
        </div></div>
    @endif
</div>
