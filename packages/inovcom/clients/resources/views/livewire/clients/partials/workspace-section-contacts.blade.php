<section class="client-360-block" style="margin-top:20px;">
    <h4 class="card-title" style="font-size:15px;">Contacts</h4>
    @if ($client->contacts->count() > 0)
        <div class="table-scroll">
            <table>
                <thead><tr><th>Nom</th><th>Rôle</th><th>Fonction</th><th>Téléphone</th><th>Email</th><th></th></tr></thead>
                <tbody>
                    @foreach ($client->contacts as $contact)
                        <tr>
                            <td>{{ $contact->full_name }}</td>
                            <td>
                                <span class="badge badge-info">{{ $contact->roleLabel() }}</span>
                                @if($contact->is_primary)<span class="badge badge-success">Principal</span>@endif
                            </td>
                            <td>{{ $contact->position ?? '—' }}</td>
                            <td>{{ $contact->phone ?? $contact->mobile ?? '—' }}</td>
                            <td>{{ $contact->email ?? '—' }}</td>
                            <td>
                                @if ($contact->phone || $contact->mobile)
                                    <a class="btn btn-secondary btn-sm" href="tel:{{ $contact->phone ?? $contact->mobile }}">Appeler</a>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="alert" style="margin:0;">Aucun contact enregistré.</div>
    @endif
</section>

<section class="client-360-block" style="margin-top:20px;">
    <h4 class="card-title" style="font-size:15px;">Adresses</h4>
    @if ($client->addresses->count() > 0)
        <div class="table-scroll">
            <table>
                <thead><tr><th>Type</th><th>Rue</th><th>Ville</th><th>Région</th><th>Pays</th><th>Défaut</th></tr></thead>
                <tbody>
                    @foreach ($client->addresses as $address)
                        <tr>
                            <td>{{ ['billing' => 'Facturation', 'shipping' => 'Livraison', 'both' => 'Les deux'][$address->type] ?? $address->type }}</td>
                            <td>{{ $address->street ?? '—' }}</td>
                            <td>{{ $address->city ?? '—' }}</td>
                            <td>{{ $address->state ?? '—' }}</td>
                            <td>{{ $address->country ?? '—' }}</td>
                            <td>@if($address->is_default)<span class="badge badge-success">Oui</span>@endif</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @else
        <div class="alert" style="margin:0;">Aucune adresse enregistrée.</div>
    @endif
</section>

<section class="client-360-block" style="margin-top:20px;">
    <h4 class="card-title" style="font-size:15px;">Identité complémentaire</h4>
    <dl class="client-dl">
        <div class="client-dl__row"><dt>Email</dt><dd>{{ $client->email ?? '—' }}</dd></div>
        <div class="client-dl__row"><dt>Téléphone</dt><dd>{{ $client->phone ?? '—' }}</dd></div>
        <div class="client-dl__row"><dt>Adresse</dt><dd>{{ $client->address ?? '—' }}</dd></div>
        <div class="client-dl__row"><dt>BP</dt><dd>{{ $client->bp ?? '—' }}</dd></div>
        <div class="client-dl__row"><dt>Segment / Zone</dt><dd>{{ $client->segment->name ?? '—' }} · {{ $client->zone->name ?? '—' }}</dd></div>
        <div class="client-dl__row"><dt>Commercial</dt><dd>{{ $salesrep->name ?? '—' }}</dd></div>
        <div class="client-dl__row"><dt>Paiement</dt><dd>{{ $paymentTerm ? $paymentTerm->name . ' (' . $paymentTerm->days . ' j)' : '—' }} · {{ $client->paymentMethodLabel() }}</dd></div>
    </dl>
</section>
