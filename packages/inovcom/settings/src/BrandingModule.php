<?php

namespace InovCom\Branding;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class BrandingModule implements ModuleLifecycle
{
    public function install(object $tenant): void
    {
        // Branding module doesn't require initial data setup
        // Logo and welcome message are set via the UI
    }

    public function uninstall(object $tenant): void
    {
        // Optional: Cleanup branding data if needed
        // For now, we keep branding data even if module is disabled
    }
}
