<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        @php
            $tenant         = app(App\Services\TenantManager::class)->tenant();
            $shopName       = $tenant ? $tenant->getSetting('shop_name', $tenant->name) : 'Inov-Com';
            $welcomeMessage = $tenant ? $tenant->getSetting('login_welcome_message', 'Gérez votre activité en toute simplicité') : 'Gérez votre activité en toute simplicité';
        @endphp
        <title>Connexion — {{ $shopName }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="m-0 p-0 font-sans bg-[#0f172a]">

        <div class="flex min-h-screen">

            {{-- ══════════════════ LEFT — Branding ══════════════════ --}}
            <div class="relative flex-[0_0_52%] hidden md:flex flex-col justify-between px-14 py-12 overflow-hidden bg-[#0f172a]">

                {{-- Geometric background --}}
                <div class="absolute inset-0"
                     style="background: radial-gradient(ellipse 70% 60% at 20% 110%, rgba(99,102,241,.35) 0%, transparent 70%), radial-gradient(ellipse 55% 50% at 85% -10%, rgba(16,185,129,.2) 0%, transparent 65%), radial-gradient(ellipse 80% 80% at 50% 50%, #111827 30%, #0f172a 100%); z-index:0;"></div>
                {{-- Grid overlay --}}
                <div class="absolute inset-0"
                     style="background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px), linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px); background-size: 48px 48px; z-index:0;"></div>

                {{-- Content (above overlays) --}}
                <div class="relative z-10 flex flex-col h-full justify-between">

                    {{-- Brand wordmark --}}
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 rounded-[10px] bg-gradient-to-br from-indigo-500 to-violet-600 flex items-center justify-center shadow-lg shadow-indigo-500/50">
                            <svg width="22" height="22" fill="none" viewBox="0 0 24 24" stroke="white" stroke-width="2">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/>
                            </svg>
                        </div>
                        <div>
                            <div class="text-[17px] font-bold text-white tracking-[-0.2px]">Inov-Com ERP</div>
                            <div class="text-[11px] text-indigo-400 font-semibold tracking-[.08em] uppercase mt-px">Plateforme de gestion</div>
                        </div>
                    </div>

                    {{-- Hero --}}
                    <div class="flex-1 flex flex-col justify-center py-10">
                        <h1 class="text-[clamp(32px,4vw,52px)] font-extrabold text-white leading-[1.1] tracking-[-1.5px] mb-4">
                            Bienvenue,<br>
                            <span class="bg-gradient-to-r from-indigo-300 to-emerald-400 bg-clip-text text-transparent">{{ $shopName }}</span>
                        </h1>
                        <p class="text-[16px] text-slate-400 leading-[1.7] max-w-[380px] mb-12">{{ $welcomeMessage }}</p>

                        {{-- KPI pills --}}
                        <div class="flex gap-3 flex-wrap mb-12">
                            @foreach([['ERP','Intégré'], ['100%','Sécurisé'], ['Multi','Modules']] as [$val, $lbl])
                            <div class="rounded-xl px-5 py-3.5 min-w-[110px]"
                                 style="background:rgba(255,255,255,.06);border:1px solid rgba(255,255,255,.1);backdrop-filter:blur(8px)">
                                <div class="text-[22px] font-bold text-white leading-none mb-1">{{ $val }}</div>
                                <div class="text-[11px] text-slate-500 uppercase tracking-[.07em] font-semibold">{{ $lbl }}</div>
                            </div>
                            @endforeach
                        </div>

                        {{-- Dashboard illustration --}}
                        <div class="rounded-2xl overflow-hidden border border-white/[.08] shadow-[0_24px_60px_rgba(0,0,0,.5)] bg-[#1e293b]">
                            <svg viewBox="0 0 560 260" xmlns="http://www.w3.org/2000/svg" style="width:100%;display:block;">
                                <rect width="560" height="260" fill="#1e293b"/>
                                <rect width="560" height="40" fill="#0f172a"/>
                                <circle cx="20" cy="20" r="5" fill="#ef4444" opacity=".7"/>
                                <circle cx="36" cy="20" r="5" fill="#f59e0b" opacity=".7"/>
                                <circle cx="52" cy="20" r="5" fill="#22c55e" opacity=".7"/>
                                <rect x="72" y="13" width="80" height="14" rx="4" fill="#1e293b"/>
                                <rect x="0" y="40" width="80" height="220" fill="#0f172a"/>
                                <rect x="12" y="60" width="56" height="8" rx="3" fill="#1e293b"/>
                                <rect x="12" y="78" width="56" height="8" rx="3" fill="#6366f1" opacity=".8"/>
                                <rect x="12" y="96" width="56" height="8" rx="3" fill="#1e293b"/>
                                <rect x="12" y="114" width="56" height="8" rx="3" fill="#1e293b"/>
                                <rect x="96" y="52" width="106" height="56" rx="8" fill="#0f172a"/>
                                <rect x="110" y="64" width="40" height="6" rx="2" fill="#334155"/>
                                <rect x="110" y="76" width="60" height="10" rx="3" fill="#6366f1" opacity=".9"/>
                                <rect x="110" y="92" width="30" height="5" rx="2" fill="#22c55e" opacity=".7"/>
                                <rect x="212" y="52" width="106" height="56" rx="8" fill="#0f172a"/>
                                <rect x="226" y="64" width="40" height="6" rx="2" fill="#334155"/>
                                <rect x="226" y="76" width="60" height="10" rx="3" fill="#8b5cf6" opacity=".9"/>
                                <rect x="328" y="52" width="106" height="56" rx="8" fill="#0f172a"/>
                                <rect x="342" y="76" width="60" height="10" rx="3" fill="#10b981" opacity=".9"/>
                                <rect x="96" y="120" width="280" height="118" rx="8" fill="#0f172a"/>
                                <rect x="118" y="190" width="18" height="30" rx="3" fill="#6366f1" opacity=".8"/>
                                <rect x="144" y="175" width="18" height="45" rx="3" fill="#6366f1" opacity=".8"/>
                                <rect x="196" y="165" width="18" height="55" rx="3" fill="#8b5cf6" opacity=".9"/>
                                <rect x="248" y="155" width="18" height="65" rx="3" fill="#10b981" opacity=".9"/>
                                <rect x="300" y="148" width="18" height="72" rx="3" fill="#8b5cf6" opacity=".9"/>
                                <rect x="388" y="120" width="156" height="118" rx="8" fill="#0f172a"/>
                                <circle cx="466" cy="186" r="34" fill="none" stroke="#6366f1" stroke-width="18" stroke-dasharray="90 124" opacity=".9"/>
                                <circle cx="466" cy="186" r="34" fill="none" stroke="#10b981" stroke-width="18" stroke-dasharray="45 169" stroke-dashoffset="-90" opacity=".8"/>
                                <circle cx="466" cy="186" r="16" fill="#0f172a"/>
                            </svg>
                        </div>
                    </div>

                    {{-- Footer --}}
                    <div class="flex items-center gap-1.5 text-[12px] text-slate-600">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-400 shadow-[0_0_6px_#22c55e] inline-block"></span>
                        Système opérationnel &nbsp;·&nbsp; Données chiffrées de bout en bout
                    </div>
                </div>
            </div>

            {{-- ══════════════════ RIGHT — Login form ══════════════════ --}}
            <div class="flex-1 bg-white flex items-center justify-center px-8 py-16 sm:px-12">
                <div class="w-full max-w-[400px]">

                    <div class="mb-9">
                        <div class="text-[12px] font-bold tracking-[.1em] uppercase text-indigo-500 mb-2.5">Espace sécurisé</div>
                        <h2 class="text-[28px] font-extrabold text-slate-900 tracking-[-0.5px] mb-2">Connexion</h2>
                        <p class="text-[14px] text-slate-500">Accédez à votre tableau de bord {{ $shopName }}</p>
                    </div>

                    <form method="POST" action="{{ route('tenant.login.submit', ['tenant' => request()->query('tenant')]) }}">
                        @csrf

                        {{-- Email --}}
                        <div class="mb-5">
                            <label class="block text-[13px] font-semibold text-slate-700 mb-1.5" for="email">Adresse email</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/>
                                    </svg>
                                </span>
                                <input
                                    class="w-full pl-10 pr-4 py-3 border-[1.5px] rounded-[10px] text-[14px] text-slate-800 bg-slate-50 outline-none transition-all duration-200 focus:border-indigo-500 focus:bg-white focus:shadow-[0_0_0_4px_rgba(99,102,241,.12)] placeholder-slate-400 {{ $errors->has('email') ? 'border-red-400' : 'border-slate-200' }}"
                                    id="email" name="email" type="email"
                                    placeholder="votre@email.com"
                                    value="{{ old('email') }}"
                                    required autofocus
                                >
                            </div>
                            @error('email')
                                <div class="flex items-center gap-1 text-[12px] text-red-500 mt-1.5">
                                    <svg width="12" height="12" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7 4a1 1 0 11-2 0 1 1 0 012 0zm-1-9a1 1 0 00-1 1v4a1 1 0 102 0V6a1 1 0 00-1-1z" clip-rule="evenodd"/></svg>
                                    {{ $message }}
                                </div>
                            @enderror
                        </div>

                        {{-- Password --}}
                        <div class="mb-6">
                            <label class="block text-[13px] font-semibold text-slate-700 mb-1.5" for="password">Mot de passe</label>
                            <div class="relative">
                                <span class="absolute left-3.5 top-1/2 -translate-y-1/2 text-slate-400 pointer-events-none">
                                    <svg width="16" height="16" fill="none" viewBox="0 0 24 24" stroke="currentColor" stroke-width="2">
                                        <path stroke-linecap="round" stroke-linejoin="round" d="M12 15v2m-6 4h12a2 2 0 002-2v-6a2 2 0 00-2-2H6a2 2 0 00-2 2v6a2 2 0 002 2zm10-10V7a4 4 0 00-8 0v4h8z"/>
                                    </svg>
                                </span>
                                <input
                                    class="w-full pl-10 pr-4 py-3 border-[1.5px] border-slate-200 rounded-[10px] text-[14px] text-slate-800 bg-slate-50 outline-none transition-all duration-200 focus:border-indigo-500 focus:bg-white focus:shadow-[0_0_0_4px_rgba(99,102,241,.12)] placeholder-slate-400"
                                    id="password" name="password" type="password"
                                    placeholder="••••••••" required
                                >
                            </div>
                        </div>

                        {{-- Remember --}}
                        <div class="flex items-center gap-2 mb-7">
                            <input type="checkbox" name="remember" id="remember" class="w-4 h-4 accent-indigo-500 cursor-pointer">
                            <label for="remember" class="text-[13px] text-slate-500 cursor-pointer select-none">Se souvenir de moi sur cet appareil</label>
                        </div>

                        <button type="submit"
                                class="w-full py-4 bg-gradient-to-r from-indigo-500 to-violet-600 text-white border-none rounded-[10px] text-[15px] font-bold cursor-pointer tracking-[.01em] transition-all duration-150 hover:-translate-y-px hover:shadow-[0_8px_24px_rgba(99,102,241,.45)] active:translate-y-0 active:opacity-90 shadow-[0_4px_16px_rgba(99,102,241,.35)] font-sans">
                            Se connecter
                        </button>

                        <div class="flex items-center gap-3 my-7 text-slate-300 text-[12px]">
                            <div class="flex-1 h-px bg-slate-200"></div>
                            connexion sécurisée
                            <div class="flex-1 h-px bg-slate-200"></div>
                        </div>

                        <p class="text-center text-[12px] text-slate-400">
                            Propulsé par <strong class="text-indigo-500 font-bold">Inov-Com ERP</strong>
                        </p>
                    </form>
                </div>
            </div>
        </div>
    </body>
</html>
