<div class="page-body">
    @include('pressing::livewire.settings.partials.nav')

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif

    <section class="card" style="padding:16px;margin-bottom:16px;">
        <h2 style="margin-top:0;">Moteur de notifications</h2>
        <p style="color:#64748b;font-size:14px;margin:0 0 12px;">
            Activez le système quand vous êtes prêt. Les modèles de textes se gèrent dans <strong>Messages</strong>.
            Les canaux externes (WhatsApp / SMS) utilisent vos propres clés API.
        </p>

        <div class="form-grid">
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label" style="display:flex;align-items:center;gap:10px;font-size:15px;">
                    <input type="checkbox" wire:model="enabled" style="width:18px;height:18px;">
                    Activer les notifications pressing
                </label>
            </div>
        </div>
    </section>

    <section class="card" style="padding:16px;margin-bottom:16px;">
        <h3 style="margin-top:0;">Canaux</h3>
        <div class="form-grid">
            <div class="field"><label class="field-label"><input type="checkbox" wire:model="in_app"> In-App (employés)</label></div>
            <div class="field"><label class="field-label"><input type="checkbox" wire:model="whatsapp"> WhatsApp (clients)</label></div>
            <div class="field"><label class="field-label"><input type="checkbox" wire:model="sms"> SMS (clients)</label></div>
            <div class="field"><label class="field-label"><input type="checkbox" wire:model="email"> Email (clients)</label></div>
        </div>
    </section>

    <section class="card" style="padding:16px;margin-bottom:16px;">
        <h3 style="margin-top:0;">WhatsApp API</h3>
        <div class="form-grid">
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">URL endpoint</label>
                <input class="input" wire:model="whatsapp_api_url" placeholder="https://votre-provider.com/v1/messages">
            </div>
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">Clé API (Bearer)</label>
                <input class="input" type="password" wire:model="whatsapp_api_key" placeholder="••••••••">
            </div>
        </div>
    </section>

    <section class="card" style="padding:16px;margin-bottom:16px;">
        <h3 style="margin-top:0;">SMS API</h3>
        <div class="form-grid">
            <div class="field" style="grid-column:1/-1;">
                <label class="field-label">URL endpoint</label>
                <input class="input" wire:model="sms_api_url" placeholder="https://votre-sms-provider.com/send">
            </div>
            <div class="field">
                <label class="field-label">Clé API</label>
                <input class="input" type="password" wire:model="sms_api_key">
            </div>
            <div class="field">
                <label class="field-label">Expéditeur</label>
                <input class="input" wire:model="sms_sender" placeholder="PRESSING">
            </div>
        </div>
    </section>

    <section class="card" style="padding:16px;margin-bottom:16px;">
        <h3 style="margin-top:0;">Email</h3>
        <div class="form-grid">
            <div class="field">
                <label class="field-label">Expéditeur (From)</label>
                <input class="input" type="email" wire:model="email_from" placeholder="noreply@votrepressing.com">
            </div>
        </div>
        <p style="color:#64748b;font-size:13px;margin-top:8px;">Utilise la config mail Laravel (.env) + cet expéditeur optionnel.</p>
    </section>

    @if ($canManage)
        <button type="button" class="btn btn-primary" wire:click="save">Enregistrer</button>
    @endif
</div>
