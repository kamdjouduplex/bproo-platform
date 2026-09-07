@php
    $fileName = $att->original_name ?: $att->label;
    $viewUrl = route('tenant.invoice_payments.attachment.download', ['invoicePayment' => $paymentId, 'invoicePaymentAttachment' => $att->id, 'tenant' => $tenantCode]);
    $downloadUrl = route('tenant.invoice_payments.attachment.download', ['invoicePayment' => $paymentId, 'invoicePaymentAttachment' => $att->id, 'tenant' => $tenantCode, 'download' => 1]);
    $canReplace = $canReplace ?? false;
@endphp
@once
<style>
    .pay-att-row { display:flex; align-items:center; gap:8px; flex-wrap:wrap; margin-bottom:8px; }
    .pay-att-name { font-size:13px; font-weight:600; color:#0f766e; text-decoration:underline; text-underline-offset:2px; word-break:break-all; }
    .pay-att-name:hover { color:#115e59; }
    .pay-att-icons { display:inline-flex; align-items:center; gap:2px; flex-shrink:0; }
    .pay-att-icon {
        display:inline-flex; align-items:center; justify-content:center;
        width:28px; height:28px; border-radius:6px; color:#4b5563; background:transparent; border:0; cursor:pointer; padding:0;
    }
    .pay-att-icon:hover { background:#f1f5f9; color:#0f766e; }
    .pay-att-icon input { position:absolute; width:1px; height:1px; opacity:0; overflow:hidden; }
</style>
@endonce
<div class="pay-att-row" wire:key="att-row-{{ $att->id }}">
    <a class="pay-att-name" href="{{ $viewUrl }}" target="_blank" rel="noopener">{{ $fileName }}</a>
    <span class="pay-att-icons">
        <a class="pay-att-icon" href="{{ $viewUrl }}" target="_blank" rel="noopener" title="Voir">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
            </svg>
        </a>
        <a class="pay-att-icon" href="{{ $downloadUrl }}" title="Télécharger">
            <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
            </svg>
        </a>
        @if ($canReplace)
            <label class="pay-att-icon" title="Mettre à jour" style="position:relative;">
                <input type="file"
                       wire:model="replaceCertificates.{{ $att->id }}"
                       accept=".pdf,.jpg,.jpeg,.png,.webp">
                <svg xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24" stroke="currentColor" width="16" height="16" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/>
                </svg>
            </label>
        @endif
    </span>
    @error('replaceCertificates.'.$att->id)
        <div class="text-error" style="width:100%;">{{ $message }}</div>
    @enderror
    <div wire:loading wire:target="replaceCertificates.{{ $att->id }}" style="font-size:12px;color:#6b7280;">Mise à jour…</div>
</div>
