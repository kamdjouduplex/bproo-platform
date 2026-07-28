<?php

namespace InovCom\Kernel\Contracts;

/**
 * Contract for tenant module lifecycle (install / uninstall).
 * Implementations receive the tenant instance when the module is toggled.
 */
interface ModuleLifecycle
{
    public function install(object $tenant): void;

    public function uninstall(object $tenant): void;
}
