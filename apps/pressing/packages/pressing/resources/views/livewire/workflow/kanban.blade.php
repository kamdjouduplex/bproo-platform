@php
    $tenantCode = request()->query('tenant') ?? session('tenant_code');
@endphp
<div class="page-body pressing-kanban"
     x-data="{
        draggedOrderId: null,
        canMove: @js($canMove),
        clearColumnTargets() {
            this.$root.querySelectorAll('.pressing-kanban__column--target').forEach((el) => {
                el.classList.remove('pressing-kanban__column--target');
            });
        },
        columnFromEvent(e) {
            return e.target.closest('[data-stage-id]');
        },
        onDragStart(e, orderId) {
            if (!this.canMove) {
                e.preventDefault();
                return;
            }
            this.draggedOrderId = orderId;
            e.dataTransfer.effectAllowed = 'move';
            e.dataTransfer.setData('text/plain', String(orderId));
            e.currentTarget.classList.add('pressing-kanban__card--dragging');
        },
        onDragEnd(e) {
            e.currentTarget.classList.remove('pressing-kanban__card--dragging');
            this.draggedOrderId = null;
            this.clearColumnTargets();
        },
        onDragOver(e) {
            if (!this.canMove) return;
            e.preventDefault();
            e.dataTransfer.dropEffect = 'move';
            this.clearColumnTargets();
            const column = this.columnFromEvent(e);
            if (column) {
                column.classList.add('pressing-kanban__column--target');
            }
        },
        async onDrop(e) {
            e.preventDefault();
            e.stopPropagation();
            this.clearColumnTargets();
            if (!this.canMove) return;

            const column = this.columnFromEvent(e);
            if (!column) return;

            const stageId = parseInt(column.dataset.stageId, 10);
            const raw = e.dataTransfer.getData('text/plain') || this.draggedOrderId;
            const orderId = parseInt(raw, 10);
            if (!orderId || !stageId) return;

            await $wire.moveOrder(orderId, stageId);
            this.draggedOrderId = null;
        },
        scrollToOrder(orderId) {
            this.$nextTick(() => {
                const el = this.$root.querySelector('[data-order-id=' + orderId + ']');
                if (!el) return;
                el.scrollIntoView({ behavior: 'smooth', block: 'nearest', inline: 'center' });
            });
        }
     }"
     x-init="$wire.$watch('focusedOrderId', (id) => { if (id) scrollToOrder(id); })">
    <style>
        .pressing-kanban__board {
            display: flex;
            gap: 12px;
            overflow-x: auto;
            padding-bottom: 8px;
            align-items: stretch;
        }
        .pressing-kanban__column {
            min-width: 260px;
            max-width: 280px;
            background: #f8fafc;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            padding: 10px;
            display: flex;
            flex-direction: column;
            transition: border-color .15s ease, background .15s ease, box-shadow .15s ease;
        }
        .pressing-kanban__column--target {
            border-color: #0ea5e9;
            background: #f0f9ff;
            box-shadow: inset 0 0 0 1px #0ea5e9;
        }
        .pressing-kanban__list {
            flex: 1;
            min-height: 140px;
            max-height: calc(100vh - 320px);
            overflow-y: auto;
        }
        .pressing-kanban__card {
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 8px;
            padding: 10px;
            margin-bottom: 8px;
            user-select: none;
            transition: opacity .15s ease, transform .15s ease, box-shadow .15s ease, border-color .15s ease;
        }
        .pressing-kanban__card--movable {
            cursor: grab;
        }
        .pressing-kanban__card--movable:active {
            cursor: grabbing;
        }
        .pressing-kanban__card--dragging {
            opacity: .45;
            transform: scale(.98);
            box-shadow: 0 8px 20px rgba(15, 23, 42, .12);
        }
        .pressing-kanban__card--focused {
            border-color: #2563eb;
            box-shadow: 0 0 0 2px rgba(37, 99, 235, .25);
        }
        .pressing-kanban__card--mine {
            border-left: 3px solid #0ea5e9;
        }
        .pressing-kanban__empty {
            color: #94a3b8;
            font-size: 13px;
            padding: 18px 8px;
            text-align: center;
            border: 1px dashed #cbd5e1;
            border-radius: 8px;
        }
        .pressing-kanban__hint {
            font-size: 12px;
            color: #64748b;
            margin-top: 4px;
        }
        .pressing-kanban__search-wrap {
            position: relative;
        }
        .pressing-kanban__results {
            position: absolute;
            left: 0;
            right: 0;
            top: calc(100% + 6px);
            z-index: 40;
            background: #fff;
            border: 1px solid #e2e8f0;
            border-radius: 10px;
            box-shadow: 0 12px 28px rgba(15, 23, 42, .12);
            max-height: 320px;
            overflow-y: auto;
        }
        .pressing-kanban__result {
            width: 100%;
            text-align: left;
            border: 0;
            background: transparent;
            padding: 10px 12px;
            cursor: pointer;
            border-bottom: 1px solid #f1f5f9;
        }
        .pressing-kanban__result:hover,
        .pressing-kanban__result:focus {
            background: #f8fafc;
            outline: none;
        }
        .pressing-kanban__chips {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
            margin-top: 10px;
        }
        .pressing-kanban__chip {
            border: 1px solid #e2e8f0;
            background: #fff;
            border-radius: 999px;
            padding: 5px 12px;
            font-size: 12px;
            font-weight: 600;
            color: #334155;
            cursor: pointer;
        }
        .pressing-kanban__chip:hover {
            border-color: #94a3b8;
            background: #f8fafc;
        }
        .pressing-kanban__chip--active {
            border-color: #2563eb;
            background: #eff6ff;
            color: #1d4ed8;
        }
    </style>

    @if (session('success'))
        <div class="alert alert-success" style="margin-bottom:16px;">{{ session('success') }}</div>
    @endif
    @if (session('error'))
        <div class="alert alert-error" style="margin-bottom:16px;">{{ session('error') }}</div>
    @endif

    <section class="card" style="padding:16px;">
        <div class="client-list-head" style="margin-bottom:12px;">
            <div>
                <h2 class="client-list-head__title">Production</h2>
                <p class="pressing-kanban__hint">
                    {{ $pipelineCount }} commande{{ $pipelineCount > 1 ? 's' : '' }} en production
                    @if (! $viewFullPipeline)
                        · <strong>filtrées sur vos assignations</strong>
                    @endif
                    @if ($lockedAgence)
                        · Agence <strong>{{ $lockedAgence->name }}</strong>
                    @endif
                </p>
            </div>
            <div class="client-list-head__actions" style="display:flex;gap:8px;flex-wrap:wrap;align-items:center;">
                @if ($canViewAllAgences)
                    <select class="input input-sm" wire:model.live="agenceFilter" style="max-width:220px;" aria-label="Filtrer par agence">
                        <option value="">Toutes les agences</option>
                        @foreach ($agences as $agence)
                            <option value="{{ $agence->id }}">{{ $agence->name }}</option>
                        @endforeach
                    </select>
                @elseif ($lockedAgence)
                    <span class="badge badge-info" title="Votre agence de travail">{{ $lockedAgence->name }}</span>
                @endif
            </div>
        </div>

        <div class="pressing-kanban__search-wrap">
            <input class="input"
                   type="search"
                   wire:model.live.debounce.250ms="search"
                   placeholder="Rechercher mes commandes (n°, client, WhatsApp, code…)"
                   aria-label="Rechercher mes commandes assignées"
                   style="width:100%;font-size:15px;padding:12px 14px;">

            @if (trim($search) !== '')
                <div class="pressing-kanban__results" role="listbox" aria-label="Résultats de recherche">
                    @forelse ($searchResults as $hit)
                        <button type="button"
                                class="pressing-kanban__result"
                                wire:click="focusOrder({{ $hit->id }})"
                                role="option">
                            <div style="font-weight:700;">{{ $hit->number }}</div>
                            <div style="font-size:13px;color:#475569;">
                                {{ $hit->client?->full_name }}
                                @if ($hit->client?->whatsapp)
                                    · {{ $hit->client->whatsapp }}
                                @endif
                            </div>
                            <div style="font-size:12px;color:#94a3b8;margin-top:2px;">
                                {{ $hit->currentStage?->name ?? 'Sans étape' }}
                                @if ($hit->agence?->name)
                                    · {{ $hit->agence->name }}
                                @endif
                            </div>
                        </button>
                    @empty
                        <div style="padding:14px 12px;color:#64748b;font-size:13px;">
                            Aucune de vos commandes ne correspond à « {{ $search }} ».
                        </div>
                    @endforelse
                </div>
            @endif
        </div>

        @if ($myAssigned->isNotEmpty() && trim($search) === '')
            <div style="margin-top:12px;">
                <div style="font-size:12px;font-weight:600;color:#64748b;margin-bottom:6px;">Mes commandes en cours</div>
                <div class="pressing-kanban__chips">
                    @foreach ($myAssigned as $mine)
                        <button type="button"
                                class="pressing-kanban__chip {{ $focusedOrderId === $mine->id ? 'pressing-kanban__chip--active' : '' }}"
                                wire:click="focusOrder({{ $mine->id }})">
                            {{ $mine->number }}
                            <span style="font-weight:500;opacity:.75;">· {{ $mine->currentStage?->name }}</span>
                        </button>
                    @endforeach
                    @if ($focusedOrderId)
                        <button type="button" class="pressing-kanban__chip" wire:click="clearFocus">Effacer focus</button>
                    @endif
                </div>
            </div>
        @endif

        @if ($canMove)
            <p class="pressing-kanban__hint" style="margin-top:12px;">
                Pipeline production uniquement.
                @if (($finCount ?? 0) > 0)
                    <strong>{{ $finCount }} commande{{ $finCount > 1 ? 's' : '' }}</strong> en Fin de production —
                    <a href="{{ route('tenant.pressing_fin_production.index', ['tenant' => $tenantCode]) }}">traiter le contrôle qualité →</a>
                @else
                    Glissez Mise en Production → Lavage → Séchage → Repassage → Fin de production.
                @endif
            </p>
        @endif

        <div class="pressing-kanban__board"
             style="margin-top:12px;"
             x-on:dragover.prevent="onDragOver($event)"
             x-on:drop.prevent="onDrop($event)">
            @foreach ($stages as $stage)
                @php
                    $columnOrders = $ordersByStage[$stage->id] ?? collect();
                @endphp
                <div class="pressing-kanban__column"
                     data-stage-id="{{ $stage->id }}">
                    <div style="display:flex;align-items:center;gap:8px;margin-bottom:10px;">
                        <span style="width:10px;height:10px;border-radius:50%;background:{{ $stage->color }};"></span>
                        <strong>{{ $stage->name }}</strong>
                        @if (($finStageId ?? null) && (int) $stage->id === (int) $finStageId && ($finCount ?? 0) > 0)
                            <span class="badge badge-info" style="font-size:10px;">{{ $finCount }} CQ</span>
                        @endif
                        <span style="margin-left:auto;color:#64748b;font-size:12px;">{{ $columnOrders->count() }}</span>
                    </div>

                    <div class="pressing-kanban__list" x-on:dragover.prevent>
                        @forelse ($columnOrders as $order)
                            @php
                                $isMine = (int) $order->assigned_user_id === (int) auth('tenant')->id()
                                    || (int) $order->receptionist_id === (int) auth('tenant')->id();
                                $isFocused = $focusedOrderId === $order->id;
                            @endphp
                            <div class="pressing-kanban__card
                                        {{ $canMove ? 'pressing-kanban__card--movable' : '' }}
                                        {{ $isMine ? 'pressing-kanban__card--mine' : '' }}
                                        {{ $isFocused ? 'pressing-kanban__card--focused' : '' }}"
                                 wire:key="kanban-order-{{ $order->id }}"
                                 data-order-id="{{ $order->id }}"
                                 @if ($canMove)
                                     draggable="true"
                                     x-on:dragover.prevent
                                     x-on:dragstart="onDragStart($event, {{ $order->id }})"
                                     x-on:dragend="onDragEnd($event)"
                                 @endif>
                                <div style="font-weight:600;">{{ $order->number }}</div>
                                <div style="font-size:13px;color:#475569;">{{ $order->client?->full_name }}</div>
                                @if ($canViewAllAgences)
                                    <div style="font-size:12px;color:#94a3b8;margin-top:4px;">{{ $order->agence?->name }}</div>
                                @endif
                                <div style="font-size:12px;color:#64748b;margin-top:6px;">
                                    {{ number_format((float) $order->total, 0, ',', ' ') }}
                                    · reste {{ number_format((float) $order->balance, 0, ',', ' ') }}
                                </div>
                                @if ($canReassign && $productionEmployees->isNotEmpty())
                                    <div style="margin-top:8px;" x-on:mousedown.stop x-on:dragstart.stop.prevent>
                                        <select class="input"
                                                style="font-size:11px;padding:4px 6px;width:100%;"
                                                wire:change="reassignOrder({{ $order->id }}, $event.target.value)"
                                                title="{{ __('Assigner à un employé production') }}">
                                            <option value="">{{ __('Assigner…') }}</option>
                                            @foreach ($productionEmployees as $employee)
                                                <option value="{{ $employee->id }}" @selected((int) $order->assigned_user_id === (int) $employee->id)>
                                                    {{ $employee->name }}
                                                </option>
                                            @endforeach
                                        </select>
                                    </div>
                                @elseif ($order->assignee)
                                    <div style="font-size:11px;color:#0ea5e9;margin-top:6px;">{{ $order->assignee->name }}</div>
                                @endif
                                @if (($finStageId ?? null) && (int) $order->current_stage_id === (int) $finStageId)
                                    <a class="btn btn-primary btn-sm"
                                       style="margin-top:8px;width:100%;font-size:11px;padding:6px 8px;"
                                       href="{{ route('tenant.pressing_fin_production.index', ['tenant' => $tenantCode]) }}">
                                        CQ & emballage
                                    </a>
                                @elseif (! $order->isSortingCompleted() && $canSort)
                                    <a class="btn btn-primary btn-sm"
                                       style="margin-top:8px;width:100%;font-size:11px;padding:6px 8px;"
                                       href="{{ route('tenant.pressing_orders.tri', ['pressingOrder' => $order->id, 'tenant' => $tenantCode]) }}">
                                        Constituer le lot
                                    </a>
                                @elseif ($order->isSortingCompleted() && $order->constitutionSummary())
                                    <div style="font-size:10px;color:#475569;margin-top:8px;line-height:1.35;" title="{{ $order->constitutionSummary() }}">
                                        {{ \Illuminate\Support\Str::limit($order->constitutionSummary(), 60) }}
                                    </div>
                                @endif
                            </div>
                        @empty
                            <div class="pressing-kanban__empty" x-on:dragover.prevent>{{ $canMove ? 'Déposer ici' : 'Vide' }}</div>
                        @endforelse
                    </div>
                </div>
            @endforeach
        </div>
    </section>
</div>
