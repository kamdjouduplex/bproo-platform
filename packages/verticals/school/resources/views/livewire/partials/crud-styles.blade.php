{{-- Shared School CRUD modal chrome — include once per page --}}
<style>
    .sch-list-head {
        display: flex;
        justify-content: space-between;
        align-items: center;
        gap: 12px;
        flex-wrap: wrap;
        padding: 16px 16px 0;
    }
    .sch-list-head__title {
        margin: 0;
        font-size: 1.05rem;
        font-weight: 700;
        color: #0f172a;
    }
    .sch-list-head__actions { display: flex; gap: 8px; flex-wrap: wrap; }
    .sch-filters {
        display: flex;
        gap: 8px;
        flex-wrap: wrap;
        padding: 12px 16px;
        align-items: center;
    }
    .sch-filters .input { min-width: 160px; }
    .sch-filters .input--grow { flex: 1; min-width: 200px; }
    .sch-row-actions {
        display: flex;
        gap: 6px;
        flex-wrap: wrap;
        justify-content: flex-end;
    }
    .sch-modal-backdrop {
        position: fixed;
        inset: 0;
        z-index: 80;
        background: rgba(15, 23, 42, 0.48);
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 16px;
    }
    .sch-modal {
        width: 100%;
        max-width: 720px;
        max-height: 90vh;
        overflow: auto;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 24px 48px rgba(15, 23, 42, 0.2);
        display: flex;
        flex-direction: column;
    }
    .sch-modal--wide { max-width: 880px; }
    .sch-modal__head {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        padding: 18px 20px 12px;
        border-bottom: 1px solid #f1f5f9;
        position: sticky;
        top: 0;
        background: #fff;
        z-index: 1;
    }
    .sch-modal__title { margin: 0; font-size: 1.05rem; font-weight: 700; color: #0f172a; }
    .sch-modal__hint { margin: 4px 0 0; font-size: 12px; color: #64748b; }
    .sch-modal__close {
        width: 32px; height: 32px; border-radius: 50%;
        border: 1px solid #e2e8f0; background: #fff; color: #64748b;
        font-size: 20px; line-height: 1; cursor: pointer; flex-shrink: 0;
    }
    .sch-modal__close:hover { background: #f1f5f9; color: #0f172a; }
    .sch-modal__body { padding: 16px 20px; }
    .sch-modal__foot {
        display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap;
        padding: 12px 20px 18px; border-top: 1px solid #f1f5f9;
        position: sticky;
        bottom: 0;
        background: #fff;
    }
    .sch-modal .form-grid {
        display: grid;
        grid-template-columns: repeat(2, minmax(0, 1fr));
        gap: 12px;
    }
    .sch-modal .form-grid--1 { grid-template-columns: 1fr; }
    .sch-modal .form-span-2 { grid-column: 1 / -1; }
    @media (max-width: 640px) {
        .sch-modal .form-grid { grid-template-columns: 1fr; }
    }
    .sch-detail-dl {
        display: grid;
        grid-template-columns: 140px 1fr;
        gap: 8px 16px;
        margin: 0;
    }
    .sch-detail-dl dt { color: #64748b; font-size: 13px; }
    .sch-detail-dl dd { margin: 0; font-weight: 500; color: #0f172a; }
    @media (max-width: 520px) {
        .sch-detail-dl { grid-template-columns: 1fr; gap: 2px 0; }
        .sch-detail-dl dt { margin-top: 8px; }
        .sch-detail-dl dt:first-child { margin-top: 0; }
    }
</style>
<script>
    window.schoolOpenPrint = window.schoolOpenPrint || function (url) {
        if (!url) {
            return false;
        }
        // window.open (pas seulement target=_blank) permet de fermer l’onglet après impression.
        window.open(url, 'bproo-school-print');
        return false;
    };
</script>
