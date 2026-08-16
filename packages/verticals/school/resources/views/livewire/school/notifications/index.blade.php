<div class="page-body">
    @include('school::livewire.partials.crud-styles')

    <section class="card app-table-card" style="margin-bottom:16px;">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Notifications SMS / Email</h2>
            <div class="sch-list-head__actions">
                <button type="button" class="btn btn-primary btn-sm" wire:click="openSettings">Paramètres</button>
            </div>
        </div>
        <div class="sch-filters" style="align-items:flex-end;">
            <div class="form-span-2" style="flex:1; min-width:220px;">
                <label class="label">Annonce rapide</label>
                <input class="input" wire:model="announceMessage" placeholder="Message…">
            </div>
            <div style="max-width:160px;"><label class="label">Tél. (optionnel)</label><input class="input" wire:model="announcePhone"></div>
            <div style="max-width:200px;"><label class="label">Email (optionnel)</label><input class="input" type="email" wire:model="announceEmail"></div>
            <button type="button" class="btn btn-secondary btn-sm" wire:click="sendAnnouncement">Envoyer</button>
        </div>
    </section>

    <section class="card app-table-card">
        <div class="sch-list-head">
            <h2 class="sch-list-head__title">Journal</h2>
        </div>
        <div class="sch-filters">
            <select class="input" wire:model.live="filterEvent" style="max-width:180px;">
                <option value="">Tous événements</option>
                <option value="enrollment">Inscription</option>
                <option value="payment">Paiement</option>
                <option value="results">Résultats</option>
                <option value="report_card">Bulletin</option>
                <option value="announcement">Annonce</option>
            </select>
            <select class="input" wire:model.live="filterChannel" style="max-width:140px;">
                <option value="">Tous canaux</option>
                <option value="sms">SMS</option>
                <option value="email">Email</option>
                <option value="system">Système</option>
            </select>
            <select class="input" wire:model.live="filterStatus" style="max-width:140px;">
                <option value="">Tous statuts</option>
                <option value="sent">Envoyé</option>
                <option value="failed">Échec</option>
                <option value="skipped">Ignoré</option>
            </select>
        </div>
        <div class="table-scroll">
            <table>
                <thead><tr><th>Date</th><th>Événement</th><th>Canal</th><th>Statut</th><th>Élève</th><th>Destinataire</th><th>Message</th></tr></thead>
                <tbody>
                @forelse($rows as $row)
                    <tr>
                        <td>{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                        <td>{{ $row->event }}</td>
                        <td>{{ $row->channel }}</td>
                        <td>{{ $row->status }}</td>
                        <td>{{ $row->student?->full_name ?? '—' }}</td>
                        <td>{{ $row->recipient ?? '—' }}</td>
                        <td style="max-width:280px; white-space:nowrap; overflow:hidden; text-overflow:ellipsis;" title="{{ $row->error ? $row->error : $row->message }}">{{ $row->message }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7">Aucun envoi enregistré.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div style="margin:12px 16px 16px;">{{ $rows->links() }}</div>
    </section>

    @if($showSettings)
        <div class="sch-modal-backdrop" wire:click.self="closeSettings">
            <div class="sch-modal sch-modal--wide">
                <div class="sch-modal__head"><h3 class="sch-modal__title">Paramètres notifications</h3><button class="sch-modal__close" wire:click="closeSettings">&times;</button></div>
                <div class="sch-modal__body"><div class="form-grid">
                    <div class="form-span-2"><label class="label"><input type="checkbox" wire:model="enabled"> Notifications activées</label></div>
                    <div><label class="label"><input type="checkbox" wire:model="channelSms"> Canal SMS</label></div>
                    <div><label class="label"><input type="checkbox" wire:model="channelEmail"> Canal Email</label></div>
                    <div class="form-span-2">
                        <label class="label">Provider SMS</label>
                        <select class="input" wire:model.live="smsDriver">
                            <option value="http">Webhook HTTP (générique)</option>
                            <option value="twilio">Twilio</option>
                        </select>
                        <p style="margin:6px 0 0; font-size:12px; color:#64748b;">Sans credentials valides, les SMS sont journalisés en « skipped ».</p>
                    </div>
                    @if($smsDriver === 'twilio')
                        <div><label class="label">Twilio Account SID</label><input class="input" wire:model="twilioSid" placeholder="ACxxxx…"></div>
                        <div><label class="label">Twilio Auth Token</label><input class="input" type="password" wire:model="twilioToken" autocomplete="off"></div>
                        <div class="form-span-2"><label class="label">Twilio From (numéro ou Messaging Service)</label><input class="input" wire:model="twilioFrom" placeholder="+237…"></div>
                    @else
                        <div class="form-span-2"><label class="label">SMS webhook URL</label><input class="input" wire:model="smsUrl" placeholder="https://…"></div>
                        <div><label class="label">SMS API key</label><input class="input" wire:model="smsKey"></div>
                        <div><label class="label">SMS sender</label><input class="input" wire:model="smsSender"></div>
                    @endif
                    <div class="form-span-2"><label class="label">Email from</label><input class="input" wire:model="emailFrom" placeholder="noreply@…"></div>
                    <div class="form-span-2"><label class="label">Modèle inscription</label><textarea class="input" rows="2" wire:model="msgEnrollment"></textarea></div>
                    <div class="form-span-2"><label class="label">Modèle paiement</label><textarea class="input" rows="2" wire:model="msgPayment"></textarea></div>
                    <div class="form-span-2"><label class="label">Modèle résultats</label><textarea class="input" rows="2" wire:model="msgResults"></textarea></div>
                    <div class="form-span-2"><label class="label">Modèle bulletin</label><textarea class="input" rows="2" wire:model="msgReportCard"></textarea></div>
                    <div class="form-span-2"><label class="label">Modèle annonce</label><textarea class="input" rows="2" wire:model="msgAnnouncement"></textarea></div>
                    <p class="form-span-2" style="color:#64748b;font-size:12px;margin:0;">Variables : @{{student}} @{{code}} @{{parent}} @{{year}} @{{class}} @{{amount}} @{{currency}} @{{reference}} @{{average}} @{{mention}} @{{message}}</p>
                </div></div>
                <div class="sch-modal__foot"><button class="btn btn-secondary" wire:click="closeSettings">Annuler</button><button class="btn btn-primary" wire:click="saveSettings">Enregistrer</button></div>
            </div>
        </div>
    @endif
</div>
