<?php

namespace Bproo\Platform\Admin;

use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

class AdminServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $views = __DIR__ . '/../../resources/views';
        if (is_dir($views)) {
            View::addLocation($views);
            $this->loadViewsFrom($views, 'platform-admin');
        }
    }
}
