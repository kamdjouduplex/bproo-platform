@php
    $settlement = $settlement ?? ['lines' => [], 'status_label' => '', 'cancelled' => false];
    $compact = $compact ?? false;
@endphp
<table class="settlement-lines" style="width:100%;border-collapse:collapse;{{ $compact ? 'font-size:12px;' : 'font-size:13px;' }}">
    <tbody>
        @foreach ($settlement['lines'] as $line)
            @php
                $isTotal = in_array($line['nature'], ['ttc', 'cash'], true);
                $color = match ($line['nature']) {
                    'cash' => '#166534',
                    'vat_withheld', 'withheld', 'tax_subtract' => '#1d4ed8',
                    'refund' => '#b91c1c',
                    'ttc' => '#111827',
                    default => '#374151',
                };
                $prefix = match ($line['display']) {
                    'minus' => '− ',
                    'plus' => '+ ',
                    default => '',
                };
            @endphp
            <tr style="{{ $isTotal ? 'border-top:1px solid #e5e7eb;' : '' }}">
                <td style="padding:4px 8px 4px 0;color:#4b5563;{{ $isTotal ? 'font-weight:600;' : '' }}">{{ $line['label'] }}</td>
                <td style="padding:4px 0;text-align:right;white-space:nowrap;font-variant-numeric:tabular-nums;color:{{ $color }};{{ $isTotal ? 'font-weight:700;' : '' }}">
                    {{ $prefix }}{{ fmt_money($line['amount']) }}
                </td>
            </tr>
        @endforeach
    </tbody>
</table>
