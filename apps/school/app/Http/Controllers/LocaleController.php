<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Schema;
use School\Support\SchoolLocaleCatalog;

class LocaleController extends Controller
{
    public function update(Request $request): RedirectResponse
    {
        $allowed = array_keys(SchoolLocaleCatalog::enabled(
            app()->bound('tenant') ? app('tenant') : null
        ));

        $locale = $request->validate([
            'locale' => ['required', 'string', 'in:'.implode(',', $allowed !== [] ? $allowed : ['fr'])],
        ])['locale'];

        session(['locale' => $locale]);
        app()->setLocale($locale);
        config(['app.locale' => $locale]);

        $user = auth('tenant')->user();
        if ($user && Schema::connection('tenant')->hasColumn('users', 'preferred_locale')) {
            $user->preferred_locale = $locale;
            $user->save();
        }

        return back();
    }
}
