<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Bulletins & relevés</h2>
            <div class="sch-list-head__actions">
                @if($filterYearId !== '')
                    <a class="btn btn-secondary btn-sm"
                       href="{{ route('tenant.school.report_cards.print', ['tenant'=>$tenantCode,'type'=>'sheet','year'=>$filterYearId] + ($filterClassId !== '' ? ['class'=>$filterClassId] : [])) }}"
                       onclick="return schoolOpenPrint(this.href)">
                        Feuille de notes (filtre)
                    </a>
                @endif
            </div>
        </div>
        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="ID, nom, parent, téléphone…">
            <select class="input" wire:model.live="filterYearId" style="max-width:200px;">
                <option value="">Toutes années</option>
                @foreach($years as $y)<option value="{{ $y->id }}">{{ $y->name }}</option>@endforeach
            </select>
            <select class="input" wire:model.live="filterClassId" style="max-width:180px;">
                <option value="">Toutes classes</option>
                @foreach($classes as $c)<option value="{{ $c->id }}">{{ $c->name }}</option>@endforeach
            </select>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Élève</th><th>ID</th><th>Année</th><th>Classe</th><th>Moyenne</th><th>Mention</th><th class="right">Documents</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($rows as $row)
                        @php $avg = $averages[$row->id] ?? null; @endphp
                        <tr>
                            <td><strong>{{ $row->student?->full_name }}</strong></td>
                            <td>{{ $row->student?->student_code }}</td>
                            <td>{{ $row->academicYear?->name }}</td>
                            <td>{{ $row->schoolClass?->name ?? '—' }}</td>
                            <td>{{ $avg['average'] !== null ? number_format((float)$avg['average'], 2) : '—' }}</td>
                            <td>{{ $avg['grade_label'] ?? '—' }}</td>
                            <td class="right">
                                <div class="sch-row-actions">
                                    <a class="btn btn-primary btn-sm"
                                       href="{{ route('tenant.school.report_cards.print', ['tenant'=>$tenantCode,'type'=>'bulletin','enrollment_ids'=>[$row->id]]) }}"
                                       onclick="return schoolOpenPrint(this.href)">Bulletin</a>
                                    <a class="btn btn-secondary btn-sm"
                                       href="{{ route('tenant.school.report_cards.print', ['tenant'=>$tenantCode,'type'=>'transcript','enrollment_ids'=>[$row->id]]) }}"
                                       onclick="return schoolOpenPrint(this.href)">Relevé</a>
                                    <a class="btn btn-secondary btn-sm"
                                       href="{{ route('tenant.school.report_cards.print', ['tenant'=>$tenantCode,'type'=>'sheet','enrollment_ids'=>[$row->id]]) }}"
                                       onclick="return schoolOpenPrint(this.href)">Notes</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="7">Aucun élève inscrit pour ce filtre.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $rows->links() }}</div>
    </section>
</div>
