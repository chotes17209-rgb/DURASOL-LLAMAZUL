<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingresar · {{ config('erp.nombre') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}">
    <meta name="theme-color" content="#1f3f95">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#eceef1]">
<div class="flex min-h-screen flex-col">
    <header class="border-b border-line bg-white" style="box-shadow: inset 0 -2px 0 var(--color-brand-800)">
        <div class="mx-auto flex h-14 max-w-5xl items-center gap-3 px-6">
            <img src="{{ asset('img/durasol.jpg') }}" alt="Mr. Durasol Perú S.A.C." class="h-9 w-auto">
            <span class="h-6 w-px bg-line"></span>
            <img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-5 w-auto">
        </div>
    </header>

    <main class="flex flex-1 items-center justify-center px-6 py-12">
        <div class="w-full max-w-[380px] rounded-sm border border-line bg-white">
            <div class="border-b border-line bg-panel px-6 py-3.5" style="box-shadow: inset 0 3px 0 var(--color-brand-800)">
                <h1 class="text-[16px] font-semibold text-slate-900">Sistema de gestión comercial</h1>
                <p class="text-[12px] text-slate-500">Ingrese con su usuario y contraseña</p>
            </div>
            <form method="POST" action="{{ route('login') }}" class="space-y-4 px-6 py-5">
                @csrf
                <div>
                    <label class="form-label" for="username">Usuario</label>
                    <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="form-input h-9 @error('username') is-invalid @enderror">
                    @error('username')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label" for="password">Contraseña</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="form-input h-9">
                </div>
                <label class="flex items-center gap-2 text-[13px] text-slate-600">
                    <input type="checkbox" name="remember" class="form-check"> Recordar sesión en este equipo
                </label>
                <button class="btn btn-primary h-9 w-full">Ingresar</button>
            </form>
            @if (config('app.demo'))
                <div class="border-t border-line px-6 py-3 text-[12px] text-slate-600">
                    <p>Usuarios de demostración (contraseña <span class="font-mono">demo1234</span>):</p>
                    <p class="mt-0.5 font-mono">admin · logistica · caja · liquidaciones</p>
                </div>
            @endif
        </div>
    </main>

    <footer class="py-4 text-center text-[12px] text-slate-500">
        © {{ now()->year }} Mr. Durasol Perú S.A.C. · Llamazul · Uso interno
    </footer>
</div>
</body>
</html>
