<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $locale = $request->validate([
            'locale' => ['required', 'in:fr,en'],
        ])['locale'];

        $tenant = app()->bound('tenant') ? app('tenant') : null;

        if ($tenant && method_exists($tenant, 'setSetting')) {
            $tenant->setSetting('locale', $locale);
        }

        session(['locale' => $locale]);
        app()->setLocale($locale);
        config(['app.locale' => $locale]);

        return back();
    }
}
