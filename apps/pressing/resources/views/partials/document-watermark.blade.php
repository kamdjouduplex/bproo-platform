{{-- Filigrane document : $watermark = ['label' => '...', 'variant' => 'paid|partial|unpaid|...'] --}}
@if (!empty($watermark['label']))
<style>
    .document-watermark {
        position: fixed;
        top: 42%;
        left: 50%;
        transform: translate(-50%, -50%) rotate(-14deg);
        font-size: 42px;
        font-weight: 800;
        letter-spacing: 2px;
        padding: 14px 36px;
        pointer-events: none;
        z-index: 0;
        border-radius: 4px;
        text-align: center;
        white-space: nowrap;
    }
    /* Contenu après le filigrane : toujours au-dessus */
    .document-watermark ~ * {
        position: relative;
        z-index: 1;
    }
    .document-watermark--paid {
        color: rgba(22, 163, 74, 0.22);
        border: 4px solid rgba(22, 163, 74, 0.28);
        background: rgba(22, 163, 74, 0.04);
    }
    .document-watermark--partial {
        color: rgba(234, 88, 12, 0.25);
        border: 4px solid rgba(234, 88, 12, 0.3);
        background: rgba(234, 88, 12, 0.05);
    }
    .document-watermark--unpaid {
        color: rgba(220, 38, 38, 0.22);
        border: 4px solid rgba(220, 38, 38, 0.28);
        background: rgba(220, 38, 38, 0.04);
    }
    .document-watermark--cancelled {
        color: rgba(107, 114, 128, 0.35);
        border: 4px solid rgba(107, 114, 128, 0.4);
        background: rgba(107, 114, 128, 0.06);
    }
    .document-watermark--draft {
        color: rgba(107, 114, 128, 0.2);
        border: 3px dashed rgba(107, 114, 128, 0.35);
        background: transparent;
        font-size: 36px;
    }
    .document-watermark--validated {
        color: rgba(22, 163, 74, 0.2);
        border: 4px solid rgba(22, 163, 74, 0.26);
        background: rgba(22, 163, 74, 0.04);
    }
    .document-watermark--rejected {
        color: rgba(220, 38, 38, 0.22);
        border: 4px solid rgba(220, 38, 38, 0.28);
        background: rgba(220, 38, 38, 0.04);
    }
    .document-watermark--suspended {
        color: rgba(234, 88, 12, 0.22);
        border: 4px solid rgba(234, 88, 12, 0.28);
        background: rgba(234, 88, 12, 0.04);
    }
    .document-watermark--sent {
        color: rgba(37, 99, 235, 0.2);
        border: 4px solid rgba(37, 99, 235, 0.26);
        background: rgba(37, 99, 235, 0.04);
    }
    @media print {
        .document-watermark {
            position: fixed;
            z-index: 0;
        }
        .document-watermark ~ * {
            position: relative;
            z-index: 1;
        }
    }
</style>
<div class="document-watermark document-watermark--{{ $watermark['variant'] ?? 'draft' }}" aria-hidden="true">
    {{ $watermark['label'] }}
</div>
@endif
