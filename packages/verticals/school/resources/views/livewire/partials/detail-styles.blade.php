{{-- Shared School detail page chrome --}}
<style>
    .sch-detail-page { display: flex; flex-direction: column; gap: 16px; }
    .sch-detail-toolbar {
        display: flex;
        justify-content: space-between;
        align-items: flex-start;
        gap: 12px;
        flex-wrap: wrap;
        padding: 16px;
    }
    .sch-detail-toolbar__title { margin: 0; font-size: 1.15rem; font-weight: 700; color: #0f172a; }
    .sch-detail-toolbar__hint { margin: 4px 0 0; font-size: 13px; color: #64748b; }
    .sch-detail-toolbar__actions { display: flex; gap: 8px; flex-wrap: wrap; align-items: center; }
    .sch-info-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(220px, 1fr));
        gap: 14px 18px;
        padding: 4px 16px 18px;
    }
    .sch-info-item__label {
        display: block;
        font-size: 12px;
        color: #64748b;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
    .sch-info-item__value {
        font-size: 15px;
        font-weight: 600;
        color: #0f172a;
        word-break: break-word;
    }
    .sch-info-item--wide { grid-column: 1 / -1; }
    .sch-actions-panel {
        padding: 16px;
        border-top: 1px solid #eef2f7;
        display: flex;
        flex-wrap: wrap;
        gap: 8px;
    }
    .sch-actions-panel__label {
        width: 100%;
        font-size: 12px;
        color: #64748b;
        margin-bottom: 4px;
        text-transform: uppercase;
        letter-spacing: .02em;
    }
</style>
@include('school::livewire.partials.crud-styles')
