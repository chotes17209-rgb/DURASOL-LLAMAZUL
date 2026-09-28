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
<body class="min-h-screen bg-white">
<div class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
    {{-- Panel institucional --}}
    <aside class="relative hidden flex-col justify-between bg-brand-900 px-12 py-10 text-white lg:flex">
        <div class="absolute inset-y-0 right-0 w-1.5 bg-accent"></div>
        <div>
            <p class="text-[11px] font-semibold tracking-[.2em] text-[#8ea3c7] uppercase">Distribución de gas licuado de petróleo</p>
            <h1 class="mt-3 text-[28px] leading-tight font-semibold">Mr. Durasol Perú S.A.C.<br><span class="text-[#b9c7df]">y Llamazul</span></h1>
        </div>
        <dl class="grid max-w-md gap-6 text-[13px]">
            <div class="border-l-2 border-white/20 pl-4">
                <dt class="font-semibold">Logística</dt>
                <dd class="mt-1 text-[#b9c7df]">Parte diario de llenos y vacíos, control de masa con planta y stock por empresa.</dd>
            </div>
            <div class="border-l-2 border-white/20 pl-4">
                <dt class="font-semibold">Liquidaciones y ventas</dt>
                <dd class="mt-1 text-[#b9c7df]">Registro por cliente, créditos, cobranzas, vales FISE y depósitos.</dd>
            </div>
            <div class="border-l-2 border-white/20 pl-4">
                <dt class="font-semibold">Precios y reportes</dt>
                <dd class="mt-1 text-[#b9c7df]">Precios de compra por instalación y de venta por cliente, con historial.</dd>
            </div>
        </dl>
        <p class="text-[11px] text-[#7d93bb]">© {{ now()->year }} Mr. Durasol Perú S.A.C. · Uso interno</p>
    </aside>

    {{-- Acceso --}}
    <main class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-sm">
            <div class="mb-10 flex items-center gap-5">
                <img src="{{ asset('img/durasol.jpg') }}" alt="Mr. Durasol Perú S.A.C." class="h-14 w-auto">
                <span class="h-10 w-px bg-line"></span>
                <img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-8 w-auto">
            </div>

            <h2 class="text-[22px] font-semibold tracking-tight text-brand-950">Ingreso al sistema</h2>
            <p class="mt-1 text-[13px] text-slate-500">Usa el usuario asignado por administración.</p>

            <form method="POST" action="{{ route('login') }}" class="mt-7 space-y-4">
                @csrf
                <div>
                    <label class="form-label" for="username">Usuario</label>
                    <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="form-input h-10 @error('username') is-invalid @enderror">
                    @error('username')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label" for="password">Contraseña</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="form-input h-10">
                </div>
                <label class="flex items-center gap-2 text-[13px] text-slate-600">
                    <input type="checkbox" name="remember" class="form-check"> Recordar sesión en este equipo
                </label>
                <button class="btn btn-primary h-10 w-full text-[14px]">Ingresar</button>
            </form>

            @if (config('app.demo'))
                <div class="help mt-8">
                    <p class="font-semibold text-slate-700">Usuarios de demostración · contraseña <span class="font-mono">demo1234</span></p>
                    <p class="mt-1 font-mono">admin · logistica · caja · liquidaciones</p>
                </div>
            @endif
        </div>
    </main>
</div>
</body>
</html>
