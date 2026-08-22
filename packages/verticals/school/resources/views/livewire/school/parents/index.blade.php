<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Parents / Tuteurs</h2>
            <div class="sch-list-head__actions">
                <span class="badge badge-secondary">{{ $totalParents }} foyer(s)</span>
                <a class="btn btn-primary btn-sm" href="{{ route('tenant.school.students.index', ['tenant' => $tenantCode]) }}">Élèves</a>
            </div>
        </div>

        <div class="sch-filters">
            <input class="input input--grow" type="search" wire:model.live.debounce.300ms="search" placeholder="Parent, téléphone, élève…">
        </div>

        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Parent / Tuteur</th>
                        <th>Téléphone</th>
                        <th>Email</th>
                        <th>Enfants</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse($parents as $parent)
                        <tr>
                            <td><strong>{{ $parent['name'] }}</strong></td>
                            <td>{{ $parent['phone'] ?: '—' }}</td>
                            <td>{{ $parent['email'] ?: '—' }}</td>
                            <td>
                                @foreach($parent['children'] as $child)
                                    <div style="margin-bottom:4px;">
                                        <a href="{{ route('tenant.school.students.show', ['tenant' => $tenantCode, 'id' => $child->id]) }}">
                                            {{ $child->student_code }} — {{ $child->full_name }}
                                        </a>
                                    </div>
                                @endforeach
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="4">Aucun parent renseigné. Complétez les fiches élèves.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin: 12px 16px 16px;">{{ $parents->links() }}</div>
    </section>
</div>
