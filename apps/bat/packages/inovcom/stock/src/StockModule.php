<?php

namespace InovCom\Stock;

use InovCom\Kernel\Contracts\ModuleLifecycle;

class StockModule implements ModuleLifecycle
{
    public function install(object $tenant): void {}

    public function uninstall(object $tenant): void {}
}
