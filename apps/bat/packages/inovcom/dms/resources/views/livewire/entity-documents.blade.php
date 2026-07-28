<div class="border border-slate-200 rounded-xl p-4">

    <div class="flex items-center justify-between mb-3">
        <span class="flex items-center gap-2 text-[13px] font-semibold text-slate-700">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/>
            </svg>
            {{ __('Documents attachés') }}
            @if($attachments->count() > 0)
                <span class="inline-block px-1.5 py-0.5 rounded-full text-[11px] font-medium bg-slate-100 text-slate-600">{{ $attachments->count() }}</span>
            @endif
        </span>
        <a href="{{ $uploadUrl }}" class="btn btn-secondary btn-sm">
            + {{ __('Ajouter un document') }}
        </a>
    </div>

    @if($attachments->count() > 0)
    <ul class="space-y-0">
        @foreach($attachments as $attachment)
            @php
                $doc  = $attachment->document;
                if ($doc) {
                    $mime = $doc->mime_type ?? '';
                    $ext  = strtolower(pathinfo($doc->filename ?? '', PATHINFO_EXTENSION));
                    [$ftBg, $ftText, $ftLabel, $ftIcon] = match(true) {
                        $doc->isPdf()
                            => ['#fee2e2','#dc2626','PDF','pdf'],
                        $doc->isImage()
                            => ['#f3e8ff','#7c3aed','IMG','image'],
                        in_array($ext,['xls','xlsx']) || str_contains($mime,'spreadsheet') || str_contains($mime,'excel')
                            => ['#dcfce7','#16a34a','XLS','spreadsheet'],
                        in_array($ext,['doc','docx']) || str_contains($mime,'wordprocessing') || str_contains($mime,'msword')
                            => ['#dbeafe','#1d4ed8','DOC','word'],
                        in_array($ext,['ppt','pptx']) || str_contains($mime,'presentation') || str_contains($mime,'powerpoint')
                            => ['#ffedd5','#c2410c','PPT','slides'],
                        in_array($ext,['zip','rar','7z','tar','gz']) || str_contains($mime,'zip') || str_contains($mime,'rar')
                            => ['#fef9c3','#a16207','ZIP','archive'],
                        $ext === 'csv' || $mime === 'text/csv'
                            => ['#d1fae5','#065f46','CSV','table'],
                        str_starts_with($mime,'video/')
                            => ['#fce7f3','#be185d','VID','video'],
                        str_starts_with($mime,'audio/')
                            => ['#e0e7ff','#4338ca','AUD','audio'],
                        str_starts_with($mime,'text/')
                            => ['#f1f5f9','#475569','TXT','text'],
                        default
                            => ['#f1f5f9','#64748b', strtoupper($ext) ?: 'FIL','file'],
                    };
                }
            @endphp
            @if($doc)
            <li class="flex items-center gap-3 py-2 border-b border-slate-100 last:border-0">
                {{-- File type badge --}}
                <div class="flex-shrink-0 w-9 h-9 rounded-lg flex items-center justify-center text-[10px] font-bold tracking-wide select-none"
                     style="background:{{ $ftBg }};color:{{ $ftText }};">
                    @if($ftIcon === 'pdf')
                        <svg viewBox="0 0 24 24" fill="currentColor" width="20" height="20" xmlns="http://www.w3.org/2000/svg"><path d="M6 2a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6H6zm7 1.5L18.5 9H13V3.5zM8.5 13h1c.8 0 1.5.7 1.5 1.5S10.3 16 9.5 16H9v1.5H8v-4.5h.5zm1 2c.3 0 .5-.2.5-.5s-.2-.5-.5-.5H9v1h.5zm2.5-2h1c1.1 0 2 .9 2 2s-.9 2-2 2H12v-4zm1 3c.6 0 1-.4 1-1s-.4-1-1-1h-.5v2H13zm3-3h2.5v1H17v1h1.5v1H17v1.5h-1V13z"/></svg>
                    @elseif($ftIcon === 'image')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.8"/><circle cx="8.5" cy="8.5" r="1.5" fill="currentColor" stroke="none"/><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M21 15l-5-5L5 21"/></svg>
                    @elseif($ftIcon === 'spreadsheet' || $ftIcon === 'table')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18"><rect x="3" y="3" width="18" height="18" rx="2" stroke-width="1.8"/><path d="M3 9h18M3 15h18M9 3v18" stroke-width="1.8"/></svg>
                    @elseif($ftIcon === 'word')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/><path stroke-width="1.8" stroke-linecap="round" d="M8 13h2m0 0l2 3m0 0l2-6m0 0l2 3"/></svg>
                    @elseif($ftIcon === 'slides')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18"><rect x="2" y="4" width="20" height="14" rx="2" stroke-width="1.8"/><path d="M8 18v2m8-2v2M6 20h12" stroke-width="1.8" stroke-linecap="round"/></svg>
                    @elseif($ftIcon === 'archive')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18"><rect x="2" y="4" width="20" height="5" rx="1" stroke-width="1.8"/><path d="M4 9v11a1 1 0 001 1h14a1 1 0 001-1V9" stroke-width="1.8"/><path d="M10 13h4" stroke-width="1.8" stroke-linecap="round"/></svg>
                    @elseif($ftIcon === 'video')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18"><rect x="2" y="5" width="15" height="14" rx="2" stroke-width="1.8"/><path d="M17 9l5-3v12l-5-3" stroke-width="1.8" stroke-linejoin="round"/></svg>
                    @elseif($ftIcon === 'audio')
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18"><path d="M9 18V5l12-2v13" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round"/><circle cx="6" cy="18" r="3" stroke-width="1.8"/><circle cx="18" cy="16" r="3" stroke-width="1.8"/></svg>
                    @else
                        <svg viewBox="0 0 24 24" fill="none" stroke="currentColor" width="18" height="18"><path stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8l-6-6z"/><path stroke-width="1.8" stroke-linecap="round" d="M9 13h6M9 17h4"/></svg>
                    @endif
                </div>
                <div class="flex-1 min-w-0">
                    <span class="block text-[13px] font-medium text-slate-700 truncate">{{ $doc->title }}</span>
                    <span class="block text-[11px] text-slate-400">{{ $doc->categoryLabel() }} · {{ $doc->humanFileSize() }} · {{ $doc->created_at->format('d/m/Y') }}</span>
                </div>
                <div class="flex items-center gap-1 flex-shrink-0">
                    <button type="button" class="table-action" wire:click="download({{ $doc->id }})" title="{{ __('Télécharger') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
                    </button>
                    <button type="button" class="table-action table-action-delete"
                            wire:click="detach({{ $doc->id }})"
                            wire:confirm="{{ __('Détacher ce document ?') }}"
                            title="{{ __('Détacher') }}">
                        <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </li>
            @endif
        @endforeach
    </ul>
    @else
        <p class="text-[13px] text-slate-400 py-4 text-center">{{ __('Aucun document attaché.') }}</p>
    @endif
</div>
