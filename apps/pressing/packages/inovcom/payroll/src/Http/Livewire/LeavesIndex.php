<?php

namespace InovCom\Payroll\Http\Livewire;

use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use InovCom\Payroll\Concerns\AuthorizesPayrollActions;
use InovCom\Payroll\Models\Employee;
use InovCom\Payroll\Models\LeaveRequest;
use InovCom\Payroll\Services\LeaveService;
use Livewire\Component;
use Livewire\WithPagination;

class LeavesIndex extends Component
{
    use AuthorizesPayrollActions;
    use WithPagination;

    public string $statusFilter = 'pending';
    public string $employeeFilter = '';

    public string $new_employee_id = '';
    public string $new_leave_type_id = '';
    public string $new_start_date = '';
    public string $new_end_date = '';
    public string $new_reason = '';

    public function mount(): void
    {
        $this->authorizePayrollAction('payroll.leave');
        app(LeaveService::class)->seedDefaultTypes();
        $this->new_start_date = now()->format('Y-m-d');
        $this->new_end_date = now()->format('Y-m-d');
    }

    public function createRequest(): void
    {
        $this->authorizePayrollAction('payroll.leave');

        $this->validate([
            'new_employee_id' => 'required|exists:tenant.employees,id',
            'new_leave_type_id' => 'required|exists:tenant.leave_types,id',
            'new_start_date' => 'required|date',
            'new_end_date' => 'required|date|after_or_equal:new_start_date',
            'new_reason' => 'nullable|string|max:500',
        ]);

        $employee = Employee::findOrFail((int) $this->new_employee_id);
        $result = app(LeaveService::class)->createRequest(
            $employee,
            (int) $this->new_leave_type_id,
            Carbon::parse($this->new_start_date),
            Carbon::parse($this->new_end_date),
            $this->new_reason ?: null,
            Auth::guard('tenant')->id()
        );

        session()->flash($result['success'] ? 'success' : 'error', $result['message']);
        if ($result['success']) {
            $this->reset(['new_employee_id', 'new_leave_type_id', 'new_reason']);
            $this->new_start_date = now()->format('Y-m-d');
            $this->new_end_date = now()->format('Y-m-d');
        }
    }

    public function approve(int $id): void
    {
        $this->authorizePayrollAction('payroll.leave');
        $request = LeaveRequest::findOrFail($id);
        $result = app(LeaveService::class)->approve($request, Auth::guard('tenant')->id());
        session()->flash($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function reject(int $id): void
    {
        $this->authorizePayrollAction('payroll.leave');
        $request = LeaveRequest::findOrFail($id);
        $result = app(LeaveService::class)->reject($request, Auth::guard('tenant')->id());
        session()->flash($result['success'] ? 'success' : 'error', $result['message']);
    }

    public function render()
    {
        $leaveService = app(LeaveService::class);

        $query = LeaveRequest::query()
            ->with(['employee', 'leaveType', 'requestedBy'])
            ->orderByDesc('start_date');

        if ($this->statusFilter !== 'all') {
            $query->where('status', $this->statusFilter);
        }
        if ($this->employeeFilter !== '') {
            $query->where('employee_id', (int) $this->employeeFilter);
        }

        return view('inovcom-payroll::livewire.leaves.index')
            ->layout('layouts.app', [
                'title' => 'Congés',
                'subtitle' => 'Demandes et soldes',
            ])
            ->with([
                'requests' => $leaveService->hasTables() ? $query->paginate(20) : collect(),
                'employees' => Employee::where('is_active', true)->orderBy('last_name')->get(),
                'leaveTypes' => $leaveService->activeTypes(),
                'tenantCode' => $this->tenantCode(),
            ]);
    }

    private function tenantCode(): ?string
    {
        return request()->query('tenant')
            ?? session()->get('tenant_code')
            ?? optional(request()->attributes->get('tenant'))->code;
    }
}
