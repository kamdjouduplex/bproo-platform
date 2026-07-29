<?php

namespace Bproo\UiCore;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Blade;

class UiCoreServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        // Register the package's Blade anonymous components so existing
        // `<x-*>` tags resolve without changing call sites.
        Blade::anonymousComponentPath(__DIR__ . '/../resources/views/components');
    }
}

