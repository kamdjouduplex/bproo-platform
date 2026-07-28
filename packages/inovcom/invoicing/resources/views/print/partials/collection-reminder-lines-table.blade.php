<table class="lines-table">
    <thead>
        <tr>
            <th>N°BC</th>
            <th>N°BL</th>
            <th>Date facture</th>
            <th>N° facture</th>
            <th>Montant TTC</th>
            <th>Date dépôt</th>
            <th>Échéance</th>
            <th>Retard</th>
            <th>Encaissé</th>
            <th>Solde</th>
        </tr>
    </thead>
    <tbody>
        @foreach ($rows as $row)
            @php $inv = $row['invoice']; @endphp
            <tr>
                <td class="left">{{ $inv->customer_reference ?? '—' }}</td>
                <td>{{ $inv->delivery_note_number ? '*' . $inv->delivery_note_number : '—' }}</td>
                <td>{{ $inv->invoice_date?->format('d/m/Y') }}</td>
                <td class="left"><strong>{{ $inv->invoice_number }}</strong></td>
                <td class="num">{{ fmt_money((float) $inv->total) }}</td>
                <td>{{ $inv->invoice_date?->format('d/m/Y') }}</td>
                <td>{{ ($row['due_date'] ?? $inv->due_date)?->format('d/m/Y') ?? '—' }}</td>
                <td class="overdue-cell">Echue depuis {{ $row['days_overdue'] }} jours</td>
                <td class="num">{{ fmt_money((float) $inv->amount_paid) }}</td>
                <td class="num"><strong>{{ fmt_money((float) $inv->balance) }}</strong></td>
            </tr>
        @endforeach
    </tbody>
</table>
