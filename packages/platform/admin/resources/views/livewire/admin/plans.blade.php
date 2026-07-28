<div class="page-body">
    <section class="card app-table-card">
        <div class="table-toolbar">
            <div class="table-title">Plans d'abonnement</div>
            <a class="btn btn-primary" href="{{ route('system.plans.create') }}">Nouveau plan</a>
        </div>
        <div class="table-scroll">
            <table>
                <thead>
                    <tr>
                        <th>Nom</th>
                        <th>Slug</th>
                        <th>Prix</th>
                        <th>Période</th>
                        <th>Actif</th>
                        <th>Actions</th>
                    </tr>
                </thead>
                <tbody>
                    @forelse ($plans as $plan)
                        <tr>
                            <td>{{ $plan->name }}</td>
                            <td><code>{{ $plan->slug }}</code></td>
                            <td>{{ fmt_money($plan->price) }} {{ $plan->currency }}</td>
                            <td>{{ $plan->billing_interval === 'yearly' ? 'Annuel' : 'Mensuel' }}</td>
                            <td>
                                @if ($plan->is_active)
                                    <span class="badge badge-success">Oui</span>
                                @else
                                    <span class="badge badge-secondary">Non</span>
                                @endif
                            </td>
                            <td>
                                <a class="btn btn-secondary" href="{{ route('system.plans.edit', $plan->id) }}">Modifier</a>
                                @if (!$plan->subscriptions()->exists())
                                    <button class="btn btn-secondary" wire:click="delete({{ $plan->id }})" wire:confirm="Supprimer ce plan ?">Supprimer</button>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6">Aucun plan. <a href="{{ route('system.plans.create') }}">Créer un plan</a>.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </section>
</div>
