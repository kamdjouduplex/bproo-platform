<?php

namespace InovCom\Planning\Http\Livewire;

use App\Livewire\Concerns\AuthorizesWithTenant;
use Carbon\Carbon;
use InovCom\Planning\Models\Appointment;
use InovCom\Planning\Models\AppointmentParticipant;
use InovCom\Clients\Models\Client;
use InovCom\Users\Models\User;
use Illuminate\Support\Facades\DB;
use Livewire\Component;

class AppointmentForm extends Component
{
    use AuthorizesWithTenant;

    public ?Appointment $appointment = null;
    public bool $isEdit = false;

    // ── Core fields ──────────────────────────────────────────────────────────
    public string $title       = '';
    public string $type        = 'visit_terrain';
    public string $status      = 'scheduled';
    public string $start_at    = '';
    public string $end_at      = '';
    public string $location    = '';
    public string $description = '';
    public string $notes       = '';
    public string $report      = '';

    public string $assigned_to           = '';
    public string $client_id             = '';
    public string $project_id            = '';
    public string $maintenance_order_id  = '';

    // ── Participants ─────────────────────────────────────────────────────────
    public array  $participants          = [];
    public bool   $showAddParticipant    = false;
    public string $newParticipantType    = 'user';
    public string $newParticipantUserId  = '';
    public string $newParticipantName    = '';
    public string $newParticipantEmail   = '';
    public string $newParticipantPhone   = '';

    protected function rules(): array
    {
        return [
            'title'       => 'required|string|max:255',
            'type'        => 'required|in:visit_terrain,reunion,maintenance,project_milestone,other',
            'status'      => 'required|in:scheduled,confirmed,done,cancelled',
            'start_at'    => 'required|date',
            'end_at'      => 'required|date|after:start_at',
            'location'    => 'nullable|string|max:500',
            'description' => 'nullable|string',
            'notes'       => 'nullable|string',
            'report'      => 'nullable|string',
            'assigned_to'           => 'nullable|integer',
            'client_id'             => 'nullable|integer',
            'project_id'            => 'nullable|integer',
            'maintenance_order_id'  => 'nullable|integer',
        ];
    }

    public function mount(?Appointment $appointment = null): void
    {
        if ($appointment && $appointment->exists) {
            $this->tenantAuthorize('planning.edit');
            $this->isEdit       = true;
            $this->appointment  = $appointment;
            $this->title        = $appointment->title;
            $this->type         = $appointment->type;
            $this->status       = $appointment->status;
            $this->start_at     = Carbon::parse($appointment->start_at)->format('Y-m-d\TH:i');
            $this->end_at       = Carbon::parse($appointment->end_at)->format('Y-m-d\TH:i');
            $this->location     = $appointment->location    ?? '';
            $this->description  = $appointment->description ?? '';
            $this->notes        = $appointment->notes       ?? '';
            $this->report       = $appointment->report      ?? '';
            $this->assigned_to  = (string) ($appointment->assigned_to          ?? '');
            $this->client_id    = (string) ($appointment->client_id             ?? '');
            $this->project_id   = (string) ($appointment->project_id            ?? '');
            $this->maintenance_order_id = (string) ($appointment->maintenance_order_id ?? '');

            $this->participants = $appointment->participants->map(fn($p) => [
                'is_external' => (bool) $p->is_external,
                'user_id'     => $p->user_id ? (string) $p->user_id : '',
                'name'        => $p->name   ?? '',
                'email'       => $p->email  ?? '',
                'phone'       => $p->phone  ?? '',
            ])->toArray();
        } else {
            $this->tenantAuthorize('planning.create');
            $next           = now()->addHour()->startOfHour();
            $this->start_at = $next->format('Y-m-d\TH:i');
            $this->end_at   = $next->copy()->addHour()->format('Y-m-d\TH:i');
            $this->assigned_to = (string) (auth('tenant')->id() ?? '');
        }
    }

    // ── Participant management ────────────────────────────────────────────────

    public function addParticipant(): void
    {
        if ($this->newParticipantType === 'user') {
            if (!$this->newParticipantUserId) {
                $this->addError('newParticipantUserId', __('Veuillez sélectionner un utilisateur.'));
                return;
            }
            // Prevent duplicates
            foreach ($this->participants as $p) {
                if (!$p['is_external'] && $p['user_id'] === $this->newParticipantUserId) {
                    $this->showAddParticipant = false;
                    $this->resetNewParticipant();
                    return;
                }
            }
            $this->participants[] = [
                'is_external' => false,
                'user_id'     => $this->newParticipantUserId,
                'name'        => '',
                'email'       => '',
                'phone'       => '',
            ];
        } else {
            if (!trim($this->newParticipantName)) {
                $this->addError('newParticipantName', __('Le nom est requis.'));
                return;
            }
            $this->participants[] = [
                'is_external' => true,
                'user_id'     => '',
                'name'        => trim($this->newParticipantName),
                'email'       => trim($this->newParticipantEmail),
                'phone'       => trim($this->newParticipantPhone),
            ];
        }

        $this->showAddParticipant = false;
        $this->resetNewParticipant();
    }

    public function removeParticipant(int $index): void
    {
        array_splice($this->participants, $index, 1);
        $this->participants = array_values($this->participants);
    }

    private function resetNewParticipant(): void
    {
        $this->newParticipantType   = 'user';
        $this->newParticipantUserId = '';
        $this->newParticipantName   = '';
        $this->newParticipantEmail  = '';
        $this->newParticipantPhone  = '';
        $this->resetErrorBag(['newParticipantUserId', 'newParticipantName']);
    }

    // ── Save ─────────────────────────────────────────────────────────────────

    public function save(): void
    {
        $this->validate();

        // Report is required to mark as done
        if ($this->status === 'done' && empty(trim($this->report))) {
            $this->addError('report', __('Un compte-rendu est obligatoire pour clôturer ce rendez-vous.'));
            return;
        }

        $data = [
            'title'       => $this->title,
            'type'        => $this->type,
            'status'      => $this->status,
            'start_at'    => Carbon::parse($this->start_at),
            'end_at'      => Carbon::parse($this->end_at),
            'location'    => $this->location    ?: null,
            'description' => $this->description ?: null,
            'notes'       => $this->notes       ?: null,
            'report'      => $this->report      ?: null,
            'assigned_to'          => $this->assigned_to          ?: null,
            'client_id'            => $this->client_id            ?: null,
            'project_id'           => $this->project_id           ?: null,
            'maintenance_order_id' => $this->maintenance_order_id ?: null,
        ];

        if ($this->isEdit) {
            $this->tenantAuthorize('planning.edit');
            $this->appointment->update($data);
            $appointment = $this->appointment;
        } else {
            $this->tenantAuthorize('planning.create');
            $max = Appointment::on('tenant')
                ->where('code', 'like', 'APT%')
                ->pluck('code')
                ->map(fn(string $c) => (int) substr($c, 3))
                ->filter(fn(int $n) => $n > 0)
                ->max();
            $data['code'] = 'APT' . str_pad((string) (($max ?? 0) + 1), 5, '0', STR_PAD_LEFT);
            $appointment = Appointment::on('tenant')->create($data);
        }

        // Sync participants (delete all, re-insert)
        AppointmentParticipant::on('tenant')
            ->where('appointment_id', $appointment->id)
            ->delete();

        foreach ($this->participants as $p) {
            AppointmentParticipant::on('tenant')->create([
                'appointment_id' => $appointment->id,
                'is_external'    => $p['is_external'],
                'user_id'        => $p['is_external'] ? null : ($p['user_id'] ?: null),
                'name'           => $p['is_external'] ? ($p['name'] ?: null) : null,
                'email'          => $p['email'] ?: null,
                'phone'          => $p['phone'] ?: null,
            ]);
        }

        notify()->success($this->isEdit ? __('Rendez-vous mis à jour.') : __('Rendez-vous créé.'));

        $this->redirect(
            route('tenant.planning.index', ['tenant' => request()->query('tenant') ?? session('tenant_code')]),
            navigate: true
        );
    }

    public function cancel(): void
    {
        $this->redirect(
            route('tenant.planning.index', ['tenant' => request()->query('tenant') ?? session('tenant_code')]),
            navigate: true
        );
    }

    public function render()
    {
        $clients = Client::on('tenant')->active()->ordered()->get(['id', 'name']);
        $users   = User::on('tenant')->orderBy('name')->get(['id', 'name']);

        $projects = DB::connection('tenant')->table('projects')
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->orderBy('title')
            ->get(['id', 'title', 'code']);

        $maintenanceOrders = DB::connection('tenant')->table('maintenance_orders')
            ->whereNotIn('status', ['closed', 'cancelled'])
            ->orderBy('code')
            ->limit(100)
            ->get(['id', 'code', 'title']);

        return view('inovcom-planning::livewire.appointment-form', [
            'clients'           => $clients,
            'users'             => $users,
            'projects'          => $projects,
            'maintenanceOrders' => $maintenanceOrders,
        ])->layout('layouts.app', [
            'title'    => $this->isEdit ? __('Modifier le rendez-vous') : __('Nouveau rendez-vous'),
            'subtitle' => $this->isEdit ? ($this->appointment->code ?? '') : '',
        ]);
    }
}
