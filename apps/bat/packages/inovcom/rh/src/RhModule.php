<?php

namespace InovCom\Rh;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class RhModule implements ModuleLifecycle
{
    public function install(object $tenant): void {}

    public function uninstall(object $tenant): void {}
}
