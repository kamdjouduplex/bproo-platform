<div class="sch-global-search">
    <style>
        .sch-global-search { position: relative; min-width: 200px; max-width: 280px; }
        .sch-global-search__input { width: 100%; min-width: 180px; }
        .sch-global-search__panel {
            position: absolute; right: 0; top: calc(100% + 6px); z-index: 80;
            width: min(360px, 90vw); max-height: 360px; overflow: auto;
            background: #fff; border: 1px solid #e2e8f0; border-radius: 10px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, 0.12);
        }
        .sch-global-search__item {
            display: block; width: 100%; text-align: left; padding: 10px 12px;
            border: 0; background: transparent; cursor: pointer; border-bottom: 1px solid #f1f5f9;
        }
        .sch-global-search__item:hover { background: #f8fafc; }
        .sch-global-search__code { font-size: 11px; color: #64748b; }
        .sch-global-search__empty { padding: 12px; font-size: 12px; color: #94a3b8; }
    </style>
    <input
        type="search"
        class="input app-select sch-global-search__input"
        wire:model.live.debounce.250ms="q"
        placeholder="Recherche élève…"
        aria-label="Recherche globale"
        autocomplete="off"
    >
    @if(mb_strlen(trim($q)) >= 2)
        <div class="sch-global-search__panel" role="listbox">
            @forelse($results as $s)
                <button type="button" class="sch-global-search__item" wire:click="selectStudent({{ $s->id }})">
                    <div><strong>{{ $s->full_name }}</strong></div>
                    <div class="sch-global-search__code">
                        {{ $s->student_code }}
                        @if($s->parent_full_name) · {{ $s->parent_full_name }} @endif
                        @if($s->parent_phone) · {{ $s->parent_phone }} @endif
                    </div>
                </button>
            @empty
                <div class="sch-global-search__empty">Aucun résultat.</div>
            @endforelse
        </div>
    @endif
</div>
