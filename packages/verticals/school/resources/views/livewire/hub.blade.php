<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Menu School</div>
        </div>

        @if (count($links) === 0)
            <div style="padding: 16px; color:#64748b;">
                Aucun module School V1 n’est encore installé.
            </div>
        @else
            <div style="display:grid; grid-template-columns: repeat(auto-fit, minmax(220px, 1fr)); gap: 12px; padding: 14px;">
                @foreach ($links as $l)
                    <a href="{{ route($l['route'], ['tenant' => request()->query('tenant') ?? request()->attributes->get('tenant')?->code]) }}"
                       style="text-decoration:none;">
                        <div style="border:1px solid #e5e7eb; border-radius:10px; padding:14px; background:#fff; min-height:84px;">
                            <div style="font-weight:700; margin-bottom:4px;">{{ $l['label'] }}</div>
                            @if (!empty($l['hint']))
                                <div style="color:#64748b; font-size:12px; line-height:1.3;">{{ $l['hint'] }}</div>
                            @endif
                        </div>
                    </a>
                @endforeach
            </div>
        @endif
    </section>
</div>

