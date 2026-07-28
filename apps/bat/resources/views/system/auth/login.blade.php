<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>Connexion admin — {{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-[#0f172a] flex items-center justify-center p-8"
          style="background: radial-gradient(ellipse 80% 60% at 50% 0%, rgba(99,102,241,.15) 0%, transparent 60%), #0f172a;">

        <div class="w-full max-w-[900px] grid md:grid-cols-2 gap-8 bg-white border border-slate-200 shadow-xl rounded-2xl p-8 md:p-10">

            {{-- Left: branding info --}}
            <div class="flex flex-col justify-between gap-6">
                <div>
                    <div class="flex items-center gap-3 mb-8">
                        <div class="w-9 h-9 rounded-xl bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-md shadow-indigo-500/40">
                            <svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2.2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                        </div>
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full bg-indigo-50 text-indigo-600 text-[11px] font-bold tracking-wide">
                            Inov-Com Admin
                        </span>
                    </div>

                    <h1 class="text-[22px] font-bold text-slate-900 mb-2">Portail d'administration</h1>
                    <p class="text-[13px] text-slate-500 leading-relaxed mb-6">Accès central pour la supervision multi-entreprises et la gestion des modules.</p>

                    <ul class="flex flex-col gap-2.5 text-[12px] text-slate-500">
                        @foreach(['Supervision multi-entreprises et modules', 'Rapports, paramètres et sécurité', 'Gestion des licences et plans'] as $item)
                        <li class="flex items-center gap-2">
                            <svg class="text-indigo-400 shrink-0" width="14" height="14" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2.5">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7"/>
                            </svg>
                            {{ $item }}
                        </li>
                        @endforeach
                    </ul>
                </div>

                <div class="text-[11px] text-slate-300">© {{ date('Y') }} Inov-Com ERP</div>
            </div>

            {{-- Right: form --}}
            <div class="flex flex-col justify-center">
                <h2 class="text-[18px] font-bold text-slate-800 mb-6">Connexion administrateur</h2>

                <form method="POST" action="{{ route('system.login.submit') }}" class="flex flex-col gap-4">
                    @csrf

                    <div class="field">
                        <label class="field-label" for="email">Email</label>
                        <input class="input {{ $errors->has('email') ? 'border-red-400' : '' }}" id="email" name="email" type="email" required autofocus>
                        @error('email')
                            <div class="field-error">{{ $message }}</div>
                        @enderror
                    </div>

                    <div class="field">
                        <label class="field-label" for="password">Mot de passe</label>
                        <input class="input" id="password" name="password" type="password" required>
                    </div>

                    <label class="flex items-center gap-2 text-[12px] text-slate-500 cursor-pointer select-none">
                        <input type="checkbox" name="remember" class="accent-indigo-500">
                        Se souvenir de moi
                    </label>

                    <button type="submit" class="btn btn-primary mt-2 w-full justify-center text-[14px] h-10">
                        Se connecter
                    </button>
                </form>
            </div>
        </div>
    </body>
</html>
