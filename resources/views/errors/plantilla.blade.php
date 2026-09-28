<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $titulo }} · {{ config('erp.nombre') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center bg-[#f2f4f8] px-6">
    <div class="w-full max-w-md rounded-lg border border-slate-200 bg-white p-8 text-center shadow-sm">
        <img src="{{ asset('img/durasol.jpg') }}" alt="Mr. Durasol Perú S.A.C." class="mx-auto h-10 w-auto">
        <p class="mt-6 text-[13px] font-semibold text-slate-400">Error {{ $codigo }}</p>
        <h1 class="mt-1 text-[20px] font-semibold text-slate-900">{{ $titulo }}</h1>
        <p class="mt-2 text-[13px] leading-relaxed text-slate-600">{{ $mensaje }}</p>
        <div class="mt-6 flex justify-center gap-2">
            <a href="javascript:history.back()" class="btn btn-secondary">Volver</a>
            <a href="{{ url('/') }}" class="btn btn-primary">Ir al panel de control</a>
        </div>
    </div>
</body>
</html>
