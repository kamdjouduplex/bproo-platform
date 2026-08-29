<?php

namespace InovCom\Debts\Http\Livewire;

use InovCom\Clients\Models\Client;
use InovCom\Debts\Models\Debt;
use InovCom\Debts\Services\DebtsService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;

class DebtForm extends Component
{
    public ?int $debtId = null;

    public ?int $client_id = null;
    public string $total_amount = '0';
    public string $due_date = '';
    public string $opened_at = '';
    public ?string $description = null;

    public string $clientSearch = '';
    public array $clientResults = [];

    public function mount(?Debt $debt = null): void
    {
        if (!$debt) {
            $this->opened_at = now()->format('Y-m-d');
            return;
        }

        $this->debtId = $debt->id;
        $this->client_id = $debt->client_id;
        $this->total_amount = (string) $debt->total_amount;
        $this->due_date = $debt->due_date?->format('Y-m-d') ?? '';
        $this->opened_at = $debt->opened_at->format('Y-m-d');
        $this->description = $debt->description;
    }

    public function updatedClientSearch(): void
    {
        if (strlen(trim($this->clientSearch)) < 1) {
            $this->clientResults = [];
            return;
        }

        $term = trim($this->clientSearch);

        $clients = Client::where('is_active', true)
            ->where(fn ($q) => $q->where('name', 'like', '%' . $term . '%')
                ->orWhere('code', 'like', '%' . $term . '%')
                ->orWhere('email', 'like', '%' . $term . '%')
                ->orWhere('phone', 'like', '%' . $term . '%'))
            ->orderBy('name')
            ->limit(10)
            ->get();

        $this->clientResults = $clients->map(fn ($c) => [
            'id' => $c->id,
            'name' => $c->name,
            'code' => $c->code,
            'current_balance' => (string) ($c->current_balance ?? 0),
        ])->toArray();
    }

    public function selectClient(int $id): void
    {
        $client = Client::find($id);
        if (!$client) {
            return;
        }
        $this->client_id = $client->id;
        $this->clientSearch = $client->name . ' (' . $client->code . ')';
        $this->clientResults = [];
    }

    public function save(): void
    {
        $requiredPermission = $this->debtId ? 'debts.update' : 'debts.create';
        if (!$this->can($requiredPermission)) {
            session()->flash('error', 'Permission refusée.');
            return;
        }

        $data = $this->validate([
            'client_id' => 'required|exists:tenant.clients,id',
            'total_amount' => 'required|numeric|min:0.01',
            'due_date' => 'nullable|date',
            'opened_at' => 'required|date',
            'description' => 'nullable|string|max:1000',
        ], [], [
            'client_id' => 'client',
            'total_amount' => 'montant',
            'due_date' => 'échéance',
            'opened_at' => 'date d\'ouverture',
            'description' => 'description',
        ]);

        $data['total_amount'] = (float) $data['total_amount'];
        $data['opened_at'] = $data['opened_at'];

        $service = app(DebtsService::class);

        if ($this->debtId) {
            $debt = Debt::findOrFail($this->debtId);
            if ($debt->isPaid() || (float) $debt->balance <= 0) {
                session()->flash('error', 'Impossible de modifier une dette soldée.');
                return;
            }
            // Update: only description, due_date for existing debt; amount changes would need adjustment logic
            $debt->update([
                'due_date' => $data['due_date'] ?: null,
                'description' => $data['description'],
            ]);
            session()->flash('success', 'Dette mise à jour.');
        } else {
            $service->createDebt($data);
            session()->flash('success', 'Dette créée. Validation requise avant encaissement.');
        }

        $this->redirect(route('tenant.debts.index', ['tenant' => $this->tenantCode()]), navigate: true);
    }

    public function render()
    {
        $currentDebt = null;
        if ($this->debtId) {
            $with = ['client', 'creator', 'validator', 'payments.creator', 'schedules'];
            if (Schema::connection('tenant')->hasTable('sales')) {
                $with[] = 'sale';
            }
            $currentDebt = Debt::with($with)->find($this->debtId);
        }

        $totalPaid = $currentDebt
            ? max(0, (float) $currentDebt->total_amount - (float) $currentDebt->balance)
            : 0.0;
        $repaymentRate = $currentDebt && (float) $currentDebt->total_amount > 0
            ? min(100, max(0, ($totalPaid / (float) $currentDebt->total_amount) * 100))
            : 0.0;

        return view('inovcom-debts::livewire.debts.form')
            ->layout('layouts.app', [
                'title' => $this->debtId ? 'Modifier dette' : 'Nouvelle dette',
                'subtitle' => 'Gestion des dettes',
            ])
            ->with([
                'currentDebt' => $currentDebt,
                'totalPaid' => $totalPaid,
                'repaymentRate' => $repaymentRate,
                'canReceivePayment' => $this->can('debts.receive_payment'),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }

    private function can(string $permission): bool
    {
        $user = Auth::guard('tenant')->user();
        if (!$user) {
            return false;
        }

        if (method_exists($user, 'roles') && $user->roles()->where('name', 'admin')->exists()) {
            return true;
        }

        return method_exists($user, 'hasPermission') && $user->hasPermission($permission);
    }
}
