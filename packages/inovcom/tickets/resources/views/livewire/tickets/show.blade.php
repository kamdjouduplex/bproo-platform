<div class="page-body">
    @if (session()->has('success'))<div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>@endif
    @if (session()->has('error'))<div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>@endif

    <div style="display:flex;gap:8px;flex-wrap:wrap;margin-bottom:16px;">
        <a class="btn btn-secondary btn-sm" href="{{ route('tenant.tickets.index', ['tenant' => $tenantCode]) }}">← Liste</a>
        <span class="badge badge-info">{{ \InovCom\Tickets\Models\Ticket::statusLabel($ticket->status) }}</span>
        <span class="badge badge-secondary">{{ \InovCom\Tickets\Models\Ticket::priorityLabel($ticket->priority) }}</span>
        @if ($ticket->category)
            <span class="badge badge-secondary">{{ $ticket->category }}</span>
        @endif
    </div>

    <div style="display:grid;grid-template-columns:1fr 320px;gap:20px;align-items:start;">
        <div>
            <section class="card" style="margin-bottom:16px;">
                <h2 class="card-title" style="margin-bottom:8px;">{{ $ticket->title }}</h2>
                <p style="font-size:13px;color:#6b7280;margin-bottom:12px;">
                    {{ $ticket->ticket_number }}
                    · Créé par {{ $ticket->creator?->name ?? '—' }}
                    le {{ $ticket->created_at->format('d/m/Y H:i') }}
                </p>
                <div style="white-space:pre-wrap;font-size:14px;line-height:1.6;">{{ $ticket->description }}</div>
            </section>

            <section class="card">
                <h3 class="form-section-title">Historique</h3>
                <div style="display:flex;flex-direction:column;gap:12px;">
                    @forelse ($ticket->comments as $comment)
                        <div style="padding:12px 14px;border-radius:8px;{{ $comment->isSystem() ? 'background:#f8fafc;border:1px solid #e2e8f0;' : 'background:#fff;border:1px solid #e5e7eb;' }}">
                            <div style="display:flex;justify-content:space-between;font-size:12px;color:#6b7280;margin-bottom:6px;">
                                <strong>{{ $comment->author?->name ?? 'Système' }}</strong>
                                <span>{{ $comment->created_at->format('d/m/Y H:i') }}</span>
                            </div>
                            <div style="white-space:pre-wrap;font-size:14px;">{{ $comment->body }}</div>
                        </div>
                    @empty
                        <p style="color:#6b7280;">Aucun commentaire.</p>
                    @endforelse
                </div>

                @if ($canUpdate)
                    <div style="margin-top:20px;padding-top:16px;border-top:1px solid #e5e7eb;">
                        <label class="field-label">Ajouter un commentaire</label>
                        <textarea class="input" wire:model="newComment" rows="3" placeholder="Mise à jour, action effectuée, question…"></textarea>
                        <button type="button" class="btn btn-primary btn-sm" style="margin-top:8px;" wire:click="addComment">Publier</button>
                    </div>
                @endif
            </section>
        </div>

        <aside>
            @if ($canUpdate)
                <section class="card" style="margin-bottom:16px;">
                    <h3 class="form-section-title">Statut</h3>
                    <select class="input" wire:model="newStatus" style="margin-bottom:8px;">
                        @foreach ($statuses as $value => $label)
                            <option value="{{ $value }}">{{ $label }}</option>
                        @endforeach
                    </select>
                    <textarea class="input" wire:model="statusNote" rows="2" placeholder="Note (optionnel)" style="margin-bottom:8px;"></textarea>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="changeStatus" style="width:100%;">Mettre à jour le statut</button>
                </section>

                <section class="card" style="margin-bottom:16px;">
                    <h3 class="form-section-title">Assignation</h3>
                    <select class="input" wire:model="assigned_to" style="margin-bottom:8px;">
                        <option value="">— Non assigné —</option>
                        @foreach ($users as $user)
                            <option value="{{ $user->id }}">{{ $user->name }}</option>
                        @endforeach
                    </select>
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="assignTicket" style="width:100%;">Assigner</button>
                </section>
            @endif

            @if ($ticket->status === 'resolved' && $canClose)
                <section class="card" style="margin-bottom:16px;background:#f0fdf4;border-color:#86efac;">
                    <h3 class="form-section-title">Clôture</h3>
                    <p style="font-size:13px;color:#166534;margin-bottom:10px;">Le problème est résolu. Clôturez le ticket pour terminer le suivi.</p>
                    <button type="button" class="btn btn-primary btn-sm" wire:click="closeTicket" wire:confirm="Clôturer ce ticket ?" style="width:100%;">Clôturer le ticket</button>
                </section>
            @endif

            @if ($ticket->status === 'closed' && $canClose)
                <section class="card" style="margin-bottom:16px;">
                    <button type="button" class="btn btn-secondary btn-sm" wire:click="reopenTicket" wire:confirm="Réouvrir ce ticket ?" style="width:100%;">Réouvrir</button>
                </section>
            @endif

            <section class="card">
                <h3 class="form-section-title">Informations</h3>
                <p style="font-size:13px;margin-bottom:6px;"><strong>Assigné :</strong> {{ $ticket->assignee?->name ?? '—' }}</p>
                @if ($ticket->resolved_at)
                    <p style="font-size:13px;margin-bottom:6px;"><strong>Résolu :</strong> {{ $ticket->resolved_at->format('d/m/Y H:i') }}</p>
                @endif
                @if ($ticket->closed_at)
                    <p style="font-size:13px;margin-bottom:6px;"><strong>Clôturé :</strong> {{ $ticket->closed_at->format('d/m/Y H:i') }}
                        @if ($ticket->closer) par {{ $ticket->closer->name }} @endif
                    </p>
                @endif
            </section>
        </aside>
    </div>
</div>
