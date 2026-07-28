<?php

namespace InovCom\Returns\Http\Livewire;

use InovCom\Returns\Concerns\AuthorizesReturnActions;
use InovCom\Returns\Enums\ItemCondition;
use InovCom\Returns\Enums\ReturnStatus;
use InovCom\Returns\Models\ReturnRequest;
use InovCom\Returns\Services\CreditNoteService;
use InovCom\Returns\Services\ReturnService;
use Livewire\Component;

class ReturnShow extends Component
{
    use AuthorizesReturnActions;

    public int $returnId;

    public string $rejectReason = '';
    public string $commentBody = '';

    /** @var array<int, array{condition: string, restock: bool}> */
    public array $inspection = [];

    public function mount(int $return): void
    {
        $this->returnId = $return;
        $this->initInspection();
    }

    private function model(): ReturnRequest
    {
        return ReturnRequest::on('tenant')
            ->with(['items.reason', 'reason', 'client', 'statusHistory.performer', 'comments.author', 'attachments', 'creditNote'])
            ->findOrFail($this->returnId);
    }

    private function initInspection(): void
    {
        $return = ReturnRequest::on('tenant')->with('items')->findOrFail($this->returnId);
        foreach ($return->items as $item) {
            $this->inspection[$item->id] = [
                'condition' => $item->condition?->value ?? ItemCondition::Resellable->value,
                'restock' => $item->restock ?? true,
            ];
        }
    }

    public function submit(): void
    {
        $this->run('returns.request', fn ($s, $r) => $s->submit($r, $this->tenantUserId()), 'Retour soumis.');
    }

    public function approve(): void
    {
        $this->run('returns.approve', fn ($s, $r) => $s->approve($r, null, $this->tenantUserId()), 'Retour approuvé.');
    }

    public function reject(): void
    {
        $reason = $this->rejectReason;
        $this->run('returns.reject', fn ($s, $r) => $s->reject($r, $reason, $this->tenantUserId()), 'Retour refusé.');
        $this->rejectReason = '';
    }

    public function receive(): void
    {
        $this->run('returns.receive', fn ($s, $r) => $s->receive($r, null, $this->tenantUserId()), 'Marchandise réceptionnée.');
    }

    public function runInspection(): void
    {
        $conditions = $this->inspection;
        $this->run('returns.inspect', fn ($s, $r) => $s->inspect($r, $conditions, $this->tenantUserId()), 'Contrôle enregistré, stock réintégré.');
    }

    public function cancel(): void
    {
        $this->run('returns.cancel', fn ($s, $r) => $s->cancel($r, 'Annulé', $this->tenantUserId()), 'Retour annulé.');
    }

    public function addComment(): void
    {
        $body = $this->commentBody;
        $this->run('returns.view', fn ($s, $r) => $s->addComment($r, $body, $this->tenantUserId()), 'Commentaire ajouté.');
        $this->commentBody = '';
    }

    public function generateCreditNote()
    {
        if (! $this->can('credit_notes.create')) {
            session()->flash('error', 'Permission refusée.');

            return null;
        }

        try {
            $creditNote = app(CreditNoteService::class)->issueFromReturn($this->model(), $this->tenantUserId());
            session()->flash('success', 'Avoir ' . $creditNote->credit_note_number . ' généré.');

            return redirect()->route('tenant.returns.credit_notes.show', [
                $creditNote->id,
                'tenant' => request()->query('tenant') ?? session('tenant_code'),
            ]);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());

            return null;
        }
    }

    private function run(string $permission, callable $callback, string $success): void
    {
        if (! $this->can($permission)) {
            session()->flash('error', 'Permission refusée.');

            return;
        }

        try {
            $callback(app(ReturnService::class), $this->model());
            session()->flash('success', $success);
        } catch (\Throwable $e) {
            session()->flash('error', $e->getMessage());
        }
    }

    public function render()
    {
        $return = $this->model();

        return view('inovcom-returns::livewire.returns.show')
            ->layout('layouts.app', [
                'title' => 'Retour ' . $return->return_number,
                'subtitle' => $return->client?->name,
            ])
            ->with([
                'return' => $return,
                'conditions' => ItemCondition::options(),
                'can' => [
                    'request' => $this->can('returns.request'),
                    'approve' => $this->can('returns.approve'),
                    'reject' => $this->can('returns.reject'),
                    'receive' => $this->can('returns.receive'),
                    'inspect' => $this->can('returns.inspect'),
                    'cancel' => $this->can('returns.cancel'),
                    'creditNote' => $this->can('credit_notes.create'),
                ],
                'status' => $return->status,
                'S' => ReturnStatus::class,
            ]);
    }
}
