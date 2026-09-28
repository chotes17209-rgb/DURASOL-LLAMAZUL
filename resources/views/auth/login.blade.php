<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>Ingresar · {{ config('erp.nombre') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}?v={{ @filemtime(public_path('favicon.svg')) }}" type="image/svg+xml">
    <link rel="icon" href="{{ asset('favicon-32.png') }}?v={{ @filemtime(public_path('favicon-32.png')) }}" sizes="32x32" type="image/png">
    <link rel="apple-touch-icon" href="{{ asset('apple-touch-icon.png') }}?v={{ @filemtime(public_path('apple-touch-icon.png')) }}">
    <meta name="theme-color" content="#1f3f95">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-[#f2f4f8]">
<div class="grid min-h-screen lg:grid-cols-[minmax(0,5fr)_minmax(0,6fr)]">
    <aside class="relative hidden bg-[#122a52] px-12 py-10 text-white lg:flex lg:flex-col lg:justify-between">
        <div class="relative flex items-center gap-3">
            <img src="{{ asset('favicon.svg') }}" alt="" class="h-10 w-10 rounded-md">
            <div class="leading-tight">
                <p class="text-[15px] font-semibold">Mr. Durasol Perú S.A.C.</p>
                <p class="text-[12px] text-[#b9c7df]">y Llamazul · Distribución de GLP</p>
            </div>
        </div>
        <div class="relative max-w-md">
            <h1 class="text-[28px] leading-tight font-semibold">Sistema de gestión comercial</h1>
            <div class="mt-3 h-[3px] w-14 bg-[#d9661a]"></div>
            <dl class="mt-8 space-y-4 text-[13px]">
                @foreach (['Compras y logística' => 'Compras en planta, parte diario de almacén y stock.', 'Ventas y caja' => 'Liquidaciones, créditos, caja general y caja chica.', 'Gerencia' => 'Rentabilidad y reportes de gestión.'] as $t => $d)
                    <div class="border-l-2 border-white/25 pl-4"><dt class="font-semibold text-white">{{ $t }}</dt><dd class="mt-0.5 text-[#b9c7df]">{{ $d }}</dd></div>
                @endforeach
            </dl>
        </div>
        <p class="relative text-[12px] text-[#8ea3c7]">© {{ now()->year }} Mr. Durasol Perú S.A.C. · Uso interno</p>
    </aside>

    <main class="flex items-center justify-center px-6 py-12">
        <div class="w-full max-w-[400px]">
            <div class="mb-8 flex items-center gap-4">
                <img src="{{ asset('img/durasol.jpg') }}" alt="Mr. Durasol Perú S.A.C." class="h-12 w-auto">
                <span class="h-8 w-px bg-slate-300"></span>
                <img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-7 w-auto">
            </div>
            <div class="rounded-lg border border-slate-200 bg-white p-7 shadow-sm">
                <h2 class="text-[19px] font-semibold text-slate-900">Iniciar sesión</h2>
                <p class="mt-1 text-[13px] text-slate-500">Ingrese con el usuario asignado por administración.</p>
                <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
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
                    <button class="btn btn-primary h-10 w-full">Ingresar</button>
                </form>
            </div>
            @if (config('app.demo'))
                <div class="help mt-4">
                    <p>Usuarios de demostración (contraseña <span class="font-mono">demo1234</span>):</p>
                    <p class="mt-0.5 font-mono">admin · logistica · caja · liquidaciones</p>
                </div>
            @endif
        </div>
    </main>
</div>
</body>
</html>
