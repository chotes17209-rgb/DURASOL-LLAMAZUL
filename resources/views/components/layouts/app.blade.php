@props(['title' => null, 'breadcrumb' => null])
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ isset($title) ? $title.' · ' : '' }}{{ config('erp.nombre') }}</title>
    <link rel="icon" href="{{ asset('favicon.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('head')
</head>
<body class="min-h-screen" x-data="{ menu: false }">
@php
    $menu = \App\Support\Menu::para(auth()->user());
    $usuario = auth()->user();
    $iniciales = collect(explode(' ', $usuario->name))->filter()->take(2)->map(fn ($p) => mb_substr($p, 0, 1))->join('');
    $seccionActual = collect($menu)->first(fn ($s) => collect($s['items'])->contains(fn ($i) => request()->routeIs($i['match'])))['titulo'] ?? 'General';
@endphp

{{-- Barra corporativa: marcas, módulo actual y usuario --}}
<header class="app-header">
    <div class="app-brand">
        <button class="btn-icon lg:hidden" @click="menu = !menu" title="Menú"><x-heroicon-o-bars-3/></button>
        <a href="{{ route('dashboard') }}" class="flex items-center gap-3">
            <img src="{{ asset('img/durasol.jpg') }}" alt="Mr. Durasol Perú S.A.C." class="h-10 w-auto">
            <span class="h-7 w-px bg-line"></span>
            <img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-[22px] w-auto">
        </a>
    </div>
    <div class="flex min-w-0 flex-1 items-center justify-between gap-4 px-6">
        <div class="hidden min-w-0 md:block">
            <p class="text-[11px] font-semibold tracking-[.12em] text-slate-400 uppercase">Sistema de gestión comercial</p>
            <p class="truncate text-[15px] font-semibold text-brand-900">{{ $seccionActual }}</p>
        </div>
        <div class="ml-auto flex items-center gap-5">
            <div class="hidden text-right leading-tight sm:block">
                <p class="text-[11px] tracking-wide text-slate-400 uppercase">Fecha de trabajo</p>
                <p class="text-[13px] font-medium text-slate-700">{{ ucfirst(now()->translatedFormat('l d \\d\\e F')) }}</p>
            </div>
            <span class="hidden h-8 w-px bg-line sm:block"></span>
            <div class="flex items-center gap-3">
                <span class="app-avatar">{{ mb_strtoupper($iniciales) }}</span>
                <div class="hidden leading-tight sm:block">
                    <p class="text-[13px] font-semibold text-slate-800">{{ $usuario->name }}</p>
                    <p class="text-[11px] text-slate-500">{{ $usuario->role->label() }}</p>
                </div>
            </div>
            <form method="POST" action="{{ route('logout') }}" id="logout-form">@csrf</form>
            <button type="button" class="btn btn-secondary btn-sm" title="Cerrar sesión"
                    onclick="confirmAction({title: '¿Cerrar sesión?', confirmText: 'Salir'}).then(ok => ok && document.getElementById('logout-form').submit())">
                <x-heroicon-o-arrow-right-start-on-rectangle/> Salir
            </button>
        </div>
    </div>
</header>

{{-- Menú lateral --}}
<div x-show="menu" x-cloak class="fixed inset-0 z-20 bg-slate-900/40 lg:hidden" @click="menu = false"></div>
<aside class="app-sidebar" :class="menu ? 'translate-x-0' : '-translate-x-full'">
    <nav class="flex-1 py-3">
        @foreach ($menu as $seccion)
            <p class="nav-section">{{ $seccion['titulo'] }}</p>
            @foreach ($seccion['items'] as $item)
                <a href="{{ route($item['route']) }}" class="nav-link {{ request()->routeIs($item['match']) ? 'active' : '' }}">
                    <x-dynamic-component :component="'heroicon-o-'.$item['icon']"/>
                    <span>{{ $item['label'] }}</span>
                </a>
            @endforeach
        @endforeach
    </nav>
    <div class="border-t border-white/10 px-5 py-4 text-[11px] leading-relaxed text-slate-400">
        <p class="font-semibold text-slate-300">Mr. Durasol Perú S.A.C.</p>
        <p>Llamazul · Distribución de GLP</p>
    </div>
</aside>

<div class="pt-16 lg:pl-64">
    {{-- Encabezado de la página --}}
    <div class="page-head">
        <div class="min-w-0">
            <p class="page-crumb">
                <span>{{ $seccionActual }}</span>
                @if ($breadcrumb && $breadcrumb !== $seccionActual)<span class="text-slate-300">/</span><span>{{ $breadcrumb }}</span>@endif
            </p>
            <h1 class="page-title">{{ $title ?? 'Inicio' }}</h1>
        </div>
        @isset($actions)
            <div class="no-print flex flex-wrap items-center gap-2">{{ $actions }}</div>
        @endisset
    </div>
    <main class="px-6 py-5">
        {{ $slot }}
    </main>
</div>

{{-- Ventanas modales (se cargan por AJAX; pueden apilarse: ver → editar) --}}
<template x-for="(m, index) in $store.modal.stack" :key="m.id">
    <div class="fixed inset-0 flex items-start justify-center overflow-y-auto px-4 py-10" :style="`z-index: ${50 + index * 5}`" :data-modal-id="m.id"
         @keydown.escape.window="index === $store.modal.stack.length - 1 && $store.modal.close()">
        <div class="fixed inset-0 bg-brand-950/50" @click="$store.modal.close()"></div>
        <div class="app-modal"
             :class="{ 'max-w-md': m.size === 'sm', 'max-w-2xl': m.size === 'md', 'max-w-4xl': m.size === 'lg', 'max-w-6xl': m.size === 'xl', 'max-w-[96rem]': m.size === 'full' }">
            <div x-show="m.loading" class="flex items-center justify-center gap-2 p-10 text-[13px] text-slate-500">
                <span class="inline-block h-4 w-4 animate-spin rounded-full border-2 border-slate-300 border-t-brand-700"></span> Cargando...
            </div>
            <div data-modal-content x-show="!m.loading" x-html="m.html"></div>
        </div>
    </div>
</template>

@if (session('success'))
    <span data-flash="{{ session('success') }}" data-type="success" class="hidden"></span>
@endif
@if (session('error'))
    <span data-flash="{{ session('error') }}" data-type="error" class="hidden"></span>
@endif
@stack('scripts')
</body>
</html>
