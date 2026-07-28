<div class="page-body">
    @include('pressing::livewire.settings.partials.nav')

    <section class="card" style="padding:16px;margin-bottom:16px;">
        <h2 style="margin:0 0 6px;">Paramétrage pressing</h2>
        <p style="margin:0;color:#64748b;">
            Configurez types, tarifs, workflow, délais, taxes, messages, paiements, agences et employés.
        </p>
    </section>

    <div class="grid-cards">
        @foreach ($sections as $section)
            <a class="card"
               href="{{ route($section['route'], ['tenant' => $tenantCode]) }}"
               style="padding:16px;text-decoration:none;color:inherit;display:block;transition:border-color .15s ease, box-shadow .15s ease;"
               onmouseover="this.style.borderColor='#0ea5e9'; this.style.boxShadow='0 6px 18px rgba(14,165,233,.12)'"
               onmouseout="this.style.borderColor=''; this.style.boxShadow=''">
                <div style="font-weight:700;margin-bottom:4px;">{{ $section['title'] }}</div>
                <div style="font-size:13px;color:#64748b;line-height:1.4;">{{ $section['description'] }}</div>
            </a>
        @endforeach
    </div>
</div>
