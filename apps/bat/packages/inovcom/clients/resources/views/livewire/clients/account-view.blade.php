@php
    use InovCom\Clients\Models\ClientActivity;
    $tenantCode = request()->query('tenant') ?? session('tenant_code') ?? optional(request()->attributes->get('tenant'))->code;
    $initials   = collect(explode(' ', $client->name))->map(fn($w) => mb_strtoupper(mb_substr($w, 0, 1)))->take(2)->implode('');
    $currency   = $client->currency ?? 'XAF';
    $fmt        = fn ($n) => number_format((float)$n, 0, ',', ' ') . ' ' . $currency;

    $statusBadge = function (string $status): array {
        return match ($status) {
            'draft'                  => ['bg' => 'bg-slate-100 text-slate-500',   'label' => 'Brouillon'],
            'new'                    => ['bg' => 'bg-blue-100 text-blue-700',     'label' => 'Nouveau'],
            'sent'                   => ['bg' => 'bg-blue-100 text-blue-700',     'label' => 'Envoyé'],
            'accepted'               => ['bg' => 'bg-emerald-100 text-emerald-700','label' => 'Accepté'],
            'refused'                => ['bg' => 'bg-red-100 text-red-700',       'label' => 'Refusé'],
            'active'                 => ['bg' => 'bg-emerald-100 text-emerald-700','label' => 'Actif'],
            'in_progress', 'planned' => ['bg' => 'bg-amber-100 text-amber-700',  'label' => ucfirst($status)],
            'completed'              => ['bg' => 'bg-emerald-100 text-emerald-700','label' => 'Terminé'],
            'closed'                 => ['bg' => 'bg-slate-100 text-slate-500',   'label' => 'Clôturé'],
            'paid'                   => ['bg' => 'bg-emerald-100 text-emerald-700','label' => 'Payée'],
            'overdue'                => ['bg' => 'bg-red-100 text-red-700',       'label' => 'En retard'],
            'cancelled'              => ['bg' => 'bg-slate-100 text-slate-500',   'label' => 'Annulé'],
            'suspended'              => ['bg' => 'bg-amber-100 text-amber-700',   'label' => 'Suspendu'],
            'expired'                => ['bg' => 'bg-red-100 text-red-700',       'label' => 'Expiré'],
            'archived'               => ['bg' => 'bg-slate-100 text-slate-400',   'label' => 'Archivé'],
            default                  => ['bg' => 'bg-slate-100 text-slate-500',   'label' => ucfirst($status)],
        };
    };

    $activityIcon = function (string $type): string {
        return match ($type) {
            'call'         => 'M3 5a2 2 0 012-2h3.28a1 1 0 01.948.684l1.498 4.493a1 1 0 01-.502 1.21l-2.257 1.13a11.042 11.042 0 005.516 5.516l1.13-2.257a1 1 0 011.21-.502l4.493 1.498a1 1 0 01.684.949V19a2 2 0 01-2 2h-1C9.716 21 3 14.284 3 6V5z',
            'email'        => 'M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z',
            'meeting'      => 'M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0',
            'site_visit'   => 'M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z M15 11a3 3 0 11-6 0 3 3 0 016 0z',
            'note'         => 'M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z',
            'quote_sent'   => 'M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z',
            'invoice_sent' => 'M9 14l6-6m-5.5.5h.01m4.99 5h.01M19 21V5a2 2 0 00-2-2H7a2 2 0 00-2 2v16l3.5-2 3.5 2 3.5-2 3.5 2z',
            default        => 'M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z',
        };
    };
@endphp

<div x-data="{ tab: 'general' }">

    {{-- ── Client header ───────────────────────────────────────────────── --}}
    <div class="flex items-start gap-4 bg-white border border-slate-200 rounded-xl px-5 py-4 mb-4">
        {{-- Avatar --}}
        <div class="w-12 h-12 rounded-full bg-blue-600 flex items-center justify-center text-white text-base font-bold flex-shrink-0">
            {{ $initials }}
        </div>
        {{-- Meta --}}
        <div class="flex-1 min-w-0">
            <div class="text-lg font-bold text-slate-800 leading-tight">{{ $client->name }}</div>
            <div class="flex flex-wrap items-center gap-2 mt-1.5">
                <span class="font-mono text-[12px] text-slate-400">{{ $client->code }}</span>
                @if ($client->type === 'company')
                    <span class="badge badge-info">{{ __('Entreprise') }}</span>
                @else
                    <span class="badge" style="background:#f3e8ff;color:#7c3aed;">{{ __('Particulier') }}</span>
                @endif
                @if ($client->segment)
                    <span class="badge badge-secondary">{{ $client->segment }}</span>
                @endif
                @if ($client->is_active)
                    <span class="badge badge-success">{{ __('Actif') }}</span>
                @else
                    <span class="badge badge-warning">{{ __('Inactif') }}</span>
                @endif
                @if ($client->last_contact_at)
                    <span class="text-[11px] text-slate-400">
                        {{ __('Dernier contact') }} : {{ $client->last_contact_at->diffForHumans() }}
                    </span>
                @endif
            </div>
        </div>
        {{-- Actions --}}
        <div class="flex-shrink-0">
            <a href="{{ route('tenant.clients.compte', ['tenant' => $tenantCode]) }}" class="btn btn-secondary btn-sm">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-3.5 h-3.5"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                {{ __('Retour') }}
            </a>
        </div>
    </div>

    {{-- ── KPI cards ───────────────────────────────────────────────────── --}}
    <div class="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3 mb-4">
        @if ($kpis['offres'] > 0)
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-slate-800">{{ $kpis['offres'] }}</div>
            <div class="text-[11px] text-slate-400 uppercase tracking-wide mt-0.5">{{ __('Offres') }}</div>
        </div>
        @endif

        @if ($kpis['devis_count'] > 0)
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-slate-800">{{ $kpis['devis_count'] }}</div>
            <div class="text-[11px] text-slate-400 uppercase tracking-wide mt-0.5">{{ __('Devis') }}</div>
            <div class="text-[11px] text-slate-400 mt-0.5">{{ $kpis['devis_acceptes'] }} {{ __('accepté(s)') }}</div>
        </div>
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <div class="text-base font-bold text-slate-800 leading-tight">{{ $fmt($kpis['devis_total_ttc']) }}</div>
            <div class="text-[11px] text-slate-400 uppercase tracking-wide mt-0.5">{{ __('Volume devis') }}</div>
        </div>
        @endif

        @if ($kpis['projets_total'] > 0)
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-slate-800">
                {{ $kpis['projets_actifs'] }}<span class="text-sm font-normal text-slate-400"> / {{ $kpis['projets_total'] }}</span>
            </div>
            <div class="text-[11px] text-slate-400 uppercase tracking-wide mt-0.5">{{ __('Projets actifs') }}</div>
        </div>
        @endif

        @if ($kpis['factures_total'] > 0)
        <div class="bg-white border border-slate-200 rounded-xl p-4 text-center">
            <div class="text-base font-bold text-slate-800 leading-tight">{{ $fmt($kpis['factures_total']) }}</div>
            <div class="text-[11px] text-slate-400 uppercase tracking-wide mt-0.5">{{ __('Total facturé') }}</div>
        </div>
        @endif

        @if ($kpis['factures_dues'] > 0)
        <div class="bg-white border border-red-200 border-l-4 border-l-red-500 rounded-xl p-4 text-center">
            <div class="text-base font-bold text-red-600 leading-tight">{{ $fmt($kpis['factures_dues']) }}</div>
            <div class="text-[11px] text-slate-400 uppercase tracking-wide mt-0.5">{{ __('Montant dû') }}</div>
        </div>
        @endif

        @if ($kpis['contrats_actifs'] > 0)
        <div class="bg-white border border-blue-200 border-l-4 border-l-blue-500 rounded-xl p-4 text-center">
            <div class="text-2xl font-bold text-slate-800">{{ $kpis['contrats_actifs'] }}</div>
            <div class="text-[11px] text-slate-400 uppercase tracking-wide mt-0.5">{{ __('Contrats maintenance') }}</div>
        </div>
        @endif
    </div>

    {{-- ── Tab navigation ──────────────────────────────────────────────── --}}
    <div class="flex gap-0 border-b border-slate-200 mb-4 overflow-x-auto" role="tablist">
        @foreach ([
            'general'     => __('Vue générale'),
            'commercial'  => __('Commercial'),
            'projets'     => __('Projets'),
            'finance'     => __('Finance'),
            'maintenance' => __('Maintenance'),
            'agenda'      => __('Agenda'),
            'activites'   => __('Activités'),
        ] as $key => $label)
        <button type="button" role="tab"
            class="px-4 py-2.5 text-[13px] font-medium whitespace-nowrap border-b-2 -mb-px transition-colors"
            :class="tab === '{{ $key }}'
                ? 'border-blue-600 text-blue-700'
                : 'border-transparent text-slate-500 hover:text-slate-700'"
            @click="tab = '{{ $key }}'"
            :aria-selected="tab === '{{ $key }}'">
            {{ $label }}
        </button>
        @endforeach
    </div>

    {{-- ── Tab bodies ───────────────────────────────────────────────────── --}}

    {{-- ─ Vue générale ─────────────────────────────────────────────────── --}}
    <div x-show="tab === 'general'">
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-5">

            {{-- Informations générales --}}
            <div class="card">
                <h4 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Informations') }}</h4>
                <table class="w-full text-[13px] border-collapse">
                    @foreach ([
                        __('Email')               => $client->email,
                        __('Téléphone')           => $client->phone,
                        __('Site web')            => $client->website,
                        __('Adresse')             => $client->address,
                        __('Ville')               => trim(($client->city ?? '') . ' ' . ($client->postal_code ?? '')),
                        __('Pays')                => $client->country,
                        __('N° fiscal')           => $client->tax_id,
                        __('Devise')              => $client->currency,
                        __('Limite crédit')       => $client->credit_limit ? $fmt($client->credit_limit) : null,
                        __('Conditions paiement') => $client->payment_terms,
                        __('Notes')               => $client->notes,
                    ] as $label => $val)
                        @if ($val)
                        <tr class="border-b border-slate-50">
                            <td class="py-1.5 pr-3 text-[11px] text-slate-400 uppercase tracking-wide w-[44%]">{{ $label }}</td>
                            <td class="py-1.5 text-slate-700">{{ $val }}</td>
                        </tr>
                        @endif
                    @endforeach
                </table>
            </div>

            {{-- Contacts --}}
            <div class="card">
                <h4 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Contacts') }}</h4>
                @forelse ($contacts as $contact)
                    <div class="border border-slate-200 rounded-lg px-3.5 py-3 mb-2 last:mb-0">
                        <div class="flex items-start justify-between gap-2">
                            <div>
                                <div class="flex items-center gap-1.5 text-[13px] font-semibold text-slate-800">
                                    {{ $contact->name }}
                                    @if ($contact->is_primary)
                                        <span class="badge badge-info">{{ __('Principal') }}</span>
                                    @endif
                                </div>
                                @if ($contact->role)
                                    <div class="text-[11px] text-slate-400 mt-0.5">{{ ucfirst(str_replace('_', ' ', $contact->role)) }}</div>
                                @endif
                            </div>
                        </div>
                        <div class="flex flex-wrap gap-4 mt-2 text-[12px] text-slate-500">
                            @if ($contact->email) <span>✉ {{ $contact->email }}</span> @endif
                            @if ($contact->phone) <span>✆ {{ $contact->phone }}</span> @endif
                        </div>
                    </div>
                @empty
                    <p class="text-[13px] text-slate-400">{{ __('Aucun contact enregistré.') }}</p>
                @endforelse
            </div>
        </div>
    </div>

    {{-- ─ Commercial ────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'commercial'">
        @if ($offres->isNotEmpty())
        <h4 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Offres reçues') }}</h4>
        <div class="card overflow-hidden p-0 mb-6">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[12px]">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Titre') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Catégorie') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Date reçue') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($offres as $o)
                        @php $b = $statusBadge($o->status ?? 'new'); @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">{{ $o->code }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $o->title }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ $o->category ?? '—' }}</td>
                            <td class="px-4 py-2.5"><span class="badge {{ $b['bg'] }}">{{ $b['label'] }}</span></td>
                            <td class="px-4 py-2.5 text-slate-400 text-[11px]">{{ $o->received_at?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($devis->isNotEmpty())
        <h4 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Devis') }}</h4>
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[12px]">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Titre') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                            <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Total TTC') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Valide jusqu\'au') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($devis as $d)
                        @php $b = $statusBadge($d->status ?? 'draft'); @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">{{ $d->code }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $d->title }}</td>
                            <td class="px-4 py-2.5"><span class="badge {{ $b['bg'] }}">{{ $b['label'] }}</span></td>
                            <td class="px-4 py-2.5 text-right font-semibold text-slate-700">{{ $fmt($d->total_ttc ?? 0) }}</td>
                            <td class="px-4 py-2.5 text-slate-400 text-[11px]">{{ $d->valid_until?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($offres->isEmpty() && $devis->isEmpty())
            <p class="text-[13px] text-slate-400">{{ __('Aucune offre ni devis pour ce client.') }}</p>
        @endif
    </div>

    {{-- ─ Projets ───────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'projets'">
        @forelse ($projets as $p)
        @php $b = $statusBadge($p->status ?? 'planned'); @endphp
        <div class="border border-slate-200 rounded-xl px-4 py-3.5 mb-3">
            <div class="flex items-start justify-between gap-4">
                <div class="flex-1 min-w-0">
                    <div class="flex flex-wrap items-center gap-2 mb-1.5">
                        <span class="font-semibold text-slate-800 text-[13px]">{{ $p->title }}</span>
                        <span class="font-mono text-[11px] text-slate-400">{{ $p->code }}</span>
                        <span class="badge {{ $b['bg'] }}">{{ $b['label'] }}</span>
                        @if ($p->priority)
                            <span class="badge badge-secondary">{{ ucfirst($p->priority) }}</span>
                        @endif
                    </div>
                    <div class="flex flex-wrap gap-5 text-[12px] text-slate-500">
                        @if ($p->start_date) <span>{{ __('Début') }}: {{ \Carbon\Carbon::parse($p->start_date)->format('d/m/Y') }}</span> @endif
                        @if ($p->end_date)   <span>{{ __('Fin') }}: {{ \Carbon\Carbon::parse($p->end_date)->format('d/m/Y') }}</span> @endif
                        @if ($p->budget)     <span>{{ __('Budget') }}: {{ $fmt($p->budget) }}</span> @endif
                    </div>
                </div>
                @if (isset($p->progress_percent))
                <div class="text-center flex-shrink-0">
                    <div class="text-xl font-bold text-slate-800">{{ $p->progress_percent }}%</div>
                    <div class="text-[10px] text-slate-400 uppercase">{{ __('Avancement') }}</div>
                </div>
                @endif
            </div>
            @if (isset($p->progress_percent))
            <div class="h-1 bg-slate-100 rounded-full mt-3">
                <div class="h-1 bg-blue-600 rounded-full" style="width:{{ min(100, $p->progress_percent) }}%;"></div>
            </div>
            @endif
        </div>
        @empty
            <p class="text-[13px] text-slate-400">{{ __('Aucun projet pour ce client.') }}</p>
        @endforelse
    </div>

    {{-- ─ Finance ───────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'finance'">
        @if ($factures->isNotEmpty())
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[12px]">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('N° Facture') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Titre') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                            <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Total TTC') }}</th>
                            <th class="text-right text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Reste dû') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Émission') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Échéance') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($factures as $f)
                        @php $b = $statusBadge($f->status ?? 'draft'); $due = ($f->amount_due ?? 0); @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">{{ $f->code }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $f->title }}</td>
                            <td class="px-4 py-2.5"><span class="badge {{ $b['bg'] }}">{{ $b['label'] }}</span></td>
                            <td class="px-4 py-2.5 text-right font-semibold text-slate-700">{{ $fmt($f->total_ttc ?? 0) }}</td>
                            <td class="px-4 py-2.5 text-right font-semibold {{ $due > 0 ? 'text-red-600' : 'text-emerald-700' }}">{{ $fmt($due) }}</td>
                            <td class="px-4 py-2.5 text-slate-400 text-[11px]">{{ $f->issue_date?->format('d/m/Y') ?? '—' }}</td>
                            <td class="px-4 py-2.5 text-slate-400 text-[11px]">{{ $f->due_date?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @else
            <p class="text-[13px] text-slate-400">{{ __('Aucune facture pour ce client.') }}</p>
        @endif
    </div>

    {{-- ─ Maintenance ───────────────────────────────────────────────────── --}}
    <div x-show="tab === 'maintenance'">
        @if ($contracts->isNotEmpty())
        <h4 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Contrats') }}</h4>
        <div class="card overflow-hidden p-0 mb-6">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[12px]">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Titre') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Type') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Début') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Fin') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($contracts as $c)
                        @php $b = $statusBadge($c->status ?? 'active'); @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">{{ $c->code }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $c->title }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ ucfirst($c->type ?? '—') }}</td>
                            <td class="px-4 py-2.5"><span class="badge {{ $b['bg'] }}">{{ $b['label'] }}</span></td>
                            <td class="px-4 py-2.5 text-slate-400 text-[11px]">{{ \Carbon\Carbon::parse($c->start_date)->format('d/m/Y') }}</td>
                            <td class="px-4 py-2.5 text-slate-400 text-[11px]">{{ $c->end_date ? \Carbon\Carbon::parse($c->end_date)->format('d/m/Y') : '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($orders->isNotEmpty())
        <h4 class="text-sm font-semibold text-slate-700 mb-3">{{ __('Ordres de maintenance') }}</h4>
        <div class="card overflow-hidden p-0">
            <div class="overflow-x-auto">
                <table class="w-full border-collapse text-[12px]">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50">
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Code') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Titre') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Priorité') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Statut') }}</th>
                            <th class="text-left text-slate-400 font-semibold text-[10px] uppercase tracking-wide px-4 py-2.5">{{ __('Signalé le') }}</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($orders as $o)
                        @php $b = $statusBadge($o->status ?? 'new'); @endphp
                        <tr class="border-b border-slate-100 hover:bg-slate-50 transition-colors">
                            <td class="px-4 py-2.5 font-mono text-[11px] text-slate-500">{{ $o->code }}</td>
                            <td class="px-4 py-2.5 text-slate-700">{{ $o->title }}</td>
                            <td class="px-4 py-2.5 text-slate-500">{{ ucfirst($o->priority ?? '—') }}</td>
                            <td class="px-4 py-2.5"><span class="badge {{ $b['bg'] }}">{{ $b['label'] }}</span></td>
                            <td class="px-4 py-2.5 text-slate-400 text-[11px]">{{ $o->reported_at?->format('d/m/Y') ?? '—' }}</td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        @endif

        @if ($contracts->isEmpty() && $orders->isEmpty())
            <p class="text-[13px] text-slate-400">{{ __('Aucun contrat ni ordre de maintenance pour ce client.') }}</p>
        @endif
    </div>

    {{-- ─ Agenda ────────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'agenda'">
        @forelse ($appointments as $a)
        @php $b = $statusBadge($a->status ?? 'planned'); @endphp
        <div class="flex gap-4 py-3 border-b border-slate-100 items-start last:border-0">
            <div class="text-center w-11 flex-shrink-0">
                <div class="text-xl font-bold text-slate-800 leading-none">{{ $a->start_at?->format('d') ?? '—' }}</div>
                <div class="text-[10px] text-slate-400 uppercase">{{ $a->start_at?->translatedFormat('M Y') }}</div>
            </div>
            <div class="flex-1 min-w-0">
                <div class="flex flex-wrap items-center gap-2">
                    <span class="font-semibold text-[13px] text-slate-800">{{ $a->title }}</span>
                    <span class="badge {{ $b['bg'] }}">{{ $b['label'] }}</span>
                    @if ($a->type) <span class="badge badge-secondary">{{ ucfirst(str_replace('_',' ',$a->type)) }}</span> @endif
                </div>
                @if ($a->location)
                    <div class="text-[12px] text-slate-500 mt-1">📍 {{ $a->location }}</div>
                @endif
            </div>
            <div class="text-[11px] text-slate-400 flex-shrink-0 whitespace-nowrap">
                {{ $a->start_at?->format('H:i') }} — {{ $a->end_at?->format('H:i') }}
            </div>
        </div>
        @empty
            <p class="text-[13px] text-slate-400">{{ __('Aucun rendez-vous pour ce client.') }}</p>
        @endforelse
    </div>

    {{-- ─ Activités ─────────────────────────────────────────────────────── --}}
    <div x-show="tab === 'activites'">
        @forelse ($activities as $act)
        <div class="flex gap-4 py-3 border-b border-slate-100 items-start last:border-0">
            <div class="w-8 h-8 rounded-full bg-slate-100 flex items-center justify-center flex-shrink-0">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" class="w-[15px] h-[15px] text-slate-500">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="{{ $activityIcon($act->type) }}"/>
                </svg>
            </div>
            <div class="flex-1 min-w-0">
                <div class="text-[13px] font-medium text-slate-800">{{ $act->subject }}</div>
                @if ($act->notes)
                    <div class="text-[12px] text-slate-500 mt-0.5">{{ $act->notes }}</div>
                @endif
                <div class="text-[11px] text-slate-400 mt-1">
                    {{ $act->user?->name ?? __('Système') }} · {{ $act->activity_at->diffForHumans() }}
                </div>
            </div>
            <div class="text-[11px] text-slate-300 flex-shrink-0">{{ $act->activity_at->format('d/m/Y H:i') }}</div>
        </div>
        @empty
            <p class="text-[13px] text-slate-400">{{ __('Aucune activité enregistrée pour ce client.') }}</p>
        @endforelse
    </div>

</div>
