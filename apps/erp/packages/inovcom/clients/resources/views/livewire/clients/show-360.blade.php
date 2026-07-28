@php
    $sections = [
        'finances' => 'Finances',
        'activity' => 'Activité commerciale',
        'contacts' => 'Contacts & adresses',
        'documents' => 'Documents',
        'journal' => 'Journal',
    ];
@endphp

<div class="page-body client-workspace client-workspace--360">
    @if (session()->has('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session()->has('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    @include('inovcom-clients::livewire.clients.partials.workspace-header', [
        'currentView' => '360',
        'canUpdate' => $canUpdate,
    ])

    <section class="card" style="margin-bottom:16px;">
        @include('inovcom-clients::livewire.clients.partials.workspace-kpi')
    </section>

    <div class="client-360-layout">
        <aside class="client-360-sidebar card">
            <h3 class="card-title" style="font-size:14px;margin-bottom:12px;">Sections</h3>
            <nav class="client-360-nav" aria-label="Sections vue 360">
                @foreach ($sections as $key => $label)
                    <button type="button"
                        class="client-360-nav__btn {{ $active360Section === $key ? 'client-360-nav__btn--active' : '' }}"
                        wire:click="set360Section('{{ $key }}')">
                        {{ $label }}
                    </button>
                @endforeach
            </nav>
        </aside>

        <div class="client-360-main card">
            @if ($active360Section === 'finances')
                <h3 class="card-title">Finances & encours</h3>
                @include('inovcom-clients::livewire.clients.partials.workspace-section-finances')
            @elseif ($active360Section === 'activity')
                <h3 class="card-title">Historique commercial</h3>
                @include('inovcom-clients::livewire.clients.partials.workspace-section-activity')
            @elseif ($active360Section === 'contacts')
                <h3 class="card-title">Contacts & adresses</h3>
                @include('inovcom-clients::livewire.clients.partials.workspace-section-contacts')
            @elseif ($active360Section === 'documents')
                <h3 class="card-title">Documents</h3>
                @include('inovcom-clients::livewire.clients.partials.workspace-section-documents')
            @elseif ($active360Section === 'journal')
                <h3 class="card-title">Journal & notes</h3>
                @include('inovcom-clients::livewire.clients.partials.workspace-section-journal')
            @endif
        </div>
    </div>
</div>
