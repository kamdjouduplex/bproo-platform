<?php

namespace School\Http\Livewire\Concerns;

trait ManagesSchoolCrudUi
{
    public bool $showForm = false;

    public bool $showDetail = false;

    public ?int $editingId = null;

    public ?int $viewingId = null;

    public function cancel(): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->resetFormFields();
        $this->resetErrorBag();
    }

    public function closeDetail(): void
    {
        $this->showDetail = false;
        $this->viewingId = null;
    }

    /**
     * Reset entity form fields. Override in each component.
     */
    abstract protected function resetFormFields(): void;

    protected function openCreateForm(): void
    {
        $this->showDetail = false;
        $this->viewingId = null;
        $this->editingId = null;
        $this->resetFormFields();
        $this->resetErrorBag();
        $this->showForm = true;
    }

    protected function openEditForm(int $id): void
    {
        $this->showDetail = false;
        $this->viewingId = null;
        $this->editingId = $id;
        $this->resetErrorBag();
        $this->showForm = true;
    }

    protected function openDetail(int $id): void
    {
        $this->showForm = false;
        $this->editingId = null;
        $this->viewingId = $id;
        $this->showDetail = true;
    }
}
