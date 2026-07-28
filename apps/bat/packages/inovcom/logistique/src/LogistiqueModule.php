<?php

namespace InovCom\Logistique;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class LogistiqueModule implements ModuleLifecycle
{
    public function install(object $tenant): void {}

    public function uninstall(object $tenant): void {}
}
