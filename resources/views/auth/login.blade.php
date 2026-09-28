<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingresar · {{ config('erp.nombre') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-950">
<div class="relative flex min-h-screen items-center justify-center overflow-hidden px-4 py-10">
    <div class="pointer-events-none absolute -top-40 -left-40 h-[32rem] w-[32rem] rounded-full bg-brand-600/30 blur-3xl"></div>
    <div class="pointer-events-none absolute -right-40 -bottom-40 h-[32rem] w-[32rem] rounded-full bg-orange-500/20 blur-3xl"></div>

    <div class="relative grid w-full max-w-5xl overflow-hidden rounded-[2rem] bg-white shadow-2xl lg:grid-cols-2">
        <div class="relative hidden flex-col justify-between bg-gradient-to-br from-brand-900 via-brand-950 to-slate-950 p-10 text-white lg:flex">
            <div>
                <div class="flex items-center gap-3">
                    <div class="flex h-11 w-11 items-center justify-center rounded-2xl bg-gradient-to-br from-flame-500 to-rose-600 shadow-lg shadow-orange-500/30">
                        <x-heroicon-s-fire class="h-6 w-6"/>
                    </div>
                    <span class="text-lg font-extrabold tracking-tight">ERP Gas</span>
                </div>
                <h2 class="mt-12 text-3xl leading-tight font-extrabold tracking-tight">Distribución de gas<br>bajo control.</h2>
                <p class="mt-4 max-w-sm text-sm text-slate-300">Movimiento de masa, despachos, liquidaciones, créditos y caja de Durasol y Llamazul en un solo lugar.</p>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="rounded-2xl bg-white p-3"><img src="{{ asset('img/durasol.jpg') }}" alt="Mr. Durasol Perú S.A.C." class="h-14 w-full object-contain"></div>
                <div class="rounded-2xl bg-white p-3"><img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-14 w-full object-contain"></div>
            </div>
        </div>

        <div class="p-8 sm:p-12">
            <div class="mb-8 flex items-center gap-3 lg:hidden">
                <img src="{{ asset('img/durasol.jpg') }}" alt="Durasol" class="h-10 object-contain">
                <img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-8 object-contain">
            </div>
            <h1 class="text-2xl font-extrabold tracking-tight text-slate-900">Bienvenido</h1>
            <p class="mt-1 text-sm text-slate-500">Ingresa con tu usuario para continuar.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-8 space-y-5">
                @csrf
                <div>
                    <label class="form-label" for="username">Usuario</label>
                    <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="form-input @error('username') is-invalid @enderror" placeholder="ej. admin">
                    @error('username')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div x-data="{ show: false }">
                    <label class="form-label" for="password">Contraseña</label>
                    <div class="relative">
                        <input id="password" name="password" :type="show ? 'text' : 'password'" required autocomplete="current-password" class="form-input pr-10" placeholder="••••••••">
                        <button type="button" class="absolute inset-y-0 right-0 flex items-center px-3 text-slate-400 hover:text-slate-600" @click="show = !show">
                            <x-heroicon-o-eye class="h-4 w-4" x-show="!show"/>
                            <x-heroicon-o-eye-slash class="h-4 w-4" x-show="show" x-cloak/>
                        </button>
                    </div>
                </div>
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="remember" class="form-check"> Mantener sesión iniciada
                </label>
                <button class="btn btn-primary w-full py-3 text-base">Ingresar <x-heroicon-o-arrow-right class="h-4 w-4"/></button>
            </form>

            @if (config('app.demo'))
                <div class="mt-8 rounded-2xl bg-amber-50 p-4 text-xs text-amber-800 ring-1 ring-amber-200">
                    <p class="font-bold">Usuarios de demostración (contraseña: <span class="font-mono">demo1234</span>)</p>
                    <p class="mt-1 font-mono">admin · logistica · caja · liquidaciones</p>
                </div>
            @endif
        </div>
    </div>
</div>
</body>
</html>
