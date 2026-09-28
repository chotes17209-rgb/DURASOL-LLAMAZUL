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
<body class="flex min-h-screen flex-col bg-[#eef0f3]">
<div class="h-1.5 bg-brand-700"></div>
<main class="flex flex-1 items-center justify-center px-4 py-10">
    <div class="w-full max-w-sm">
        <div class="mb-6 flex items-center justify-center gap-4">
            <img src="{{ asset('img/durasol.jpg') }}" alt="Mr. Durasol Perú S.A.C." class="h-14 w-auto">
            <span class="h-10 w-px bg-line"></span>
            <img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-8 w-auto">
        </div>

        <div class="card">
            <div class="card-header"><p class="card-title">Ingreso al sistema</p></div>
            <form method="POST" action="{{ route('login') }}" class="space-y-4 p-5">
                @csrf
                <div>
                    <label class="form-label" for="username">Usuario</label>
                    <input id="username" name="username" value="{{ old('username') }}" required autofocus autocomplete="username" class="form-input @error('username') is-invalid @enderror">
                    @error('username')<p class="form-error">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="form-label" for="password">Contraseña</label>
                    <input id="password" name="password" type="password" required autocomplete="current-password" class="form-input">
                </div>
                <label class="flex items-center gap-2 text-[13px] text-slate-600">
                    <input type="checkbox" name="remember" class="form-check"> Recordar sesión en este equipo
                </label>
                <button class="btn btn-primary w-full">Ingresar</button>
            </form>
        </div>

        @if (config('app.demo'))
            <div class="help mt-4">
                <p class="font-semibold text-slate-700">Usuarios de demostración · contraseña <span class="font-mono">demo1234</span></p>
                <p class="mt-1 font-mono">admin · logistica · caja · liquidaciones</p>
            </div>
        @endif
        <p class="mt-6 text-center text-[11px] text-slate-400">Mr. Durasol Perú S.A.C. · Llamazul — distribución de gas licuado</p>
    </div>
</main>
</body>
</html>
