<?php

namespace InovCom\Projets\Http\Livewire;

use InovCom\Kernel\Support\ServiceCatalog;

class PrestationsIndex extends ProjectsIndex
{
    public function mount(): void
    {
        $this->typeFilter = ServiceCatalog::EXEC_SERVICE;
        parent::mount();
    }
}
