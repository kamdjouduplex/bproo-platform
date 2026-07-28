@php $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code; @endphp

<div x-data="kanban()" x-init="init()" @dragover.prevent @drop.prevent>

    {{-- Top bar --}}
    <div class="flex items-center justify-between mb-5">
        <span class="text-[15px] font-bold text-slate-900">{{ __('Pipeline des offres') }}</span>
        <div class="flex gap-2">
            <a href="{{ route('tenant.offres.index', ['tenant' => $tenantCode]) }}" class="btn btn-secondary btn-sm">
                ☰ {{ __('Vue liste') }}
            </a>
            <a href="{{ route('tenant.offres.create', ['tenant' => $tenantCode]) }}" class="btn btn-primary btn-sm">
                + {{ __('Nouvelle offre') }}
            </a>
        </div>
    </div>

    {{-- Board --}}
    <div class="flex gap-3.5 items-start overflow-x-auto pb-3">
        @foreach($columns as $status => $col)
        <div class="flex-none w-[240px] bg-slate-50 border border-slate-200 rounded-xl flex flex-col min-h-[200px] transition-colors"
             x-bind:class="{ 'bg-indigo-50 border-indigo-300': dragOverCol === '{{ $status }}' }"
             @dragover.prevent="dragOverCol = '{{ $status }}'"
             @dragleave.prevent="dragOverCol = null"
             @drop.prevent="drop('{{ $status }}')">

            <div class="flex items-center justify-between px-3.5 py-3 border-b border-slate-200">
                <span class="text-[12px] font-bold uppercase tracking-widest" style="color:{{ $col['color'] }}">{{ $col['label'] }}</span>
                <span class="text-[11px] font-bold bg-slate-200 text-slate-600 px-1.5 py-0.5 rounded-full">{{ ($offers[$status] ?? collect())->count() }}</span>
            </div>

            <div class="p-2.5 flex flex-col gap-2 flex-1 min-h-[80px]">
                @forelse(($offers[$status] ?? collect()) as $offer)
                <div class="bg-white rounded-xl border border-slate-200 px-3.5 py-3 cursor-grab hover:shadow-md transition-all relative select-none"
                     draggable="true"
                     x-bind:class="{ 'opacity-50 scale-[.97]': draggingId === {{ $offer->id }} }"
                     @dragstart="dragStart({{ $offer->id }})"
                     @dragend="dragEnd()">

                    <a href="{{ route('tenant.offres.edit', ['tenant' => $tenantCode, 'offer' => $offer->id]) }}"
                       class="absolute top-2.5 right-2.5 text-slate-300 hover:text-indigo-500 transition-colors"
                       title="{{ __('Modifier') }}">
                        <svg width="13" height="13" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                            <path stroke-linecap="round" stroke-linejoin="round" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                        </svg>
                    </a>

                    <div class="text-[10px] font-bold text-slate-400 tracking-widest mb-1">{{ $offer->code }}</div>
                    <div class="text-[13px] font-semibold text-slate-900 leading-snug mb-2 pr-4">{{ $offer->title }}</div>
                    <div class="flex flex-wrap gap-1.5">
                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded {{ \InovCom\Kernel\Support\ServiceCatalog::offerCategoryBadgeClass($offer->category) }}">
                            {{ \InovCom\Kernel\Support\ServiceCatalog::offerCategoryLabel($offer->category) }}
                        </span>
                        @if($offer->source)
                        <span class="text-[10px] font-semibold px-1.5 py-0.5 rounded bg-slate-100 text-slate-600">
                            @if($offer->source === 'call_for_tender') AO
                            @elseif($offer->source === 'recommendation') Reco.
                            @else Client
                            @endif
                        </span>
                        @endif
                    </div>
                    @if($offer->client)
                    <div class="text-[11px] text-slate-500 mt-1.5">{{ $offer->client->name }}</div>
                    @endif
                    @if($offer->received_at)
                    <div class="text-[10px] text-slate-300 mt-0.5">{{ $offer->received_at->format('d/m/Y') }}</div>
                    @endif
                </div>
                @empty
                <div class="text-center py-6 text-[12px] text-slate-300">{{ __('Aucune offre') }}</div>
                @endforelse
            </div>
        </div>
        @endforeach
    </div>
</div>

<script>
function kanban() {
    return {
        draggingId: null,
        dragOverCol: null,
        init() {},
        dragStart(id) { this.draggingId = id; },
        dragEnd() { this.draggingId = null; this.dragOverCol = null; },
        drop(status) {
            if (this.draggingId === null) return;
            const id = this.draggingId;
            this.dragEnd();
            @this.moveOffer(id, status);
        }
    }
}
</script>
