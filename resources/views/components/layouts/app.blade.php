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
@php($menu = \App\Support\Menu::para(auth()->user()))

{{-- Barra superior con las marcas --}}
<header class="fixed inset-x-0 top-0 z-30 flex h-14 items-center border-b border-line bg-white">
    <div class="flex h-full w-60 shrink-0 items-center gap-3 border-r border-line px-4 max-lg:w-auto max-lg:border-r-0">
        <button class="btn-icon lg:hidden" @click="menu = !menu" title="Menú"><x-heroicon-o-bars-3/></button>
        <a href="{{ route('dashboard') }}" class="flex items-center gap-2.5">
            <img src="{{ asset('img/durasol.jpg') }}" alt="Mr. Durasol Perú S.A.C." class="h-9 w-auto">
            <span class="h-6 w-px bg-line"></span>
            <img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-5 w-auto">
        </a>
    </div>
    <div class="flex min-w-0 flex-1 items-center justify-between gap-4 px-5">
        <p class="hidden truncate text-[13px] text-slate-500 md:block">Sistema de gestión comercial y logística</p>
        <div class="ml-auto flex items-center gap-4">
            <span class="hidden text-xs text-slate-500 sm:inline">{{ ucfirst(now()->translatedFormat('l d/m/Y')) }}</span>
            <span class="hidden h-6 w-px bg-line sm:block"></span>
            <div class="text-right leading-tight">
                <p class="text-[13px] font-semibold text-slate-800">{{ auth()->user()->name }}</p>
                <p class="text-[11px] text-slate-500">{{ auth()->user()->role->label() }}</p>
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
<div x-show="menu" x-cloak class="fixed inset-0 z-20 bg-slate-900/30 lg:hidden" @click="menu = false"></div>
<aside class="fixed top-14 bottom-0 left-0 z-20 w-60 overflow-y-auto border-r border-line bg-white pb-6 transition-transform lg:translate-x-0"
       :class="menu ? 'translate-x-0' : '-translate-x-full'">
    <nav class="pt-1">
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
</aside>

<div class="pt-14 lg:pl-60">
    <main class="px-5 py-4">
        <div class="mb-4 flex flex-wrap items-end justify-between gap-3">
            <div>
                @if ($breadcrumb)
                    <p class="text-xs text-slate-500">{{ $breadcrumb }}</p>
                @endif
                <h1 class="text-lg font-semibold text-slate-900">{{ $title ?? 'Inicio' }}</h1>
            </div>
            @isset($actions)
                <div class="no-print flex flex-wrap items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
        {{ $slot }}
    </main>
</div>

{{-- Ventanas modales (se cargan por AJAX; pueden apilarse: ver → editar) --}}
<template x-for="(m, index) in $store.modal.stack" :key="m.id">
    <div class="fixed inset-0 flex items-start justify-center overflow-y-auto px-4 py-10" :style="`z-index: ${50 + index * 5}`" :data-modal-id="m.id"
         @keydown.escape.window="index === $store.modal.stack.length - 1 && $store.modal.close()">
        <div class="fixed inset-0 bg-slate-900/40" @click="$store.modal.close()"></div>
        <div class="relative w-full rounded-md border border-line bg-white shadow-xl"
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
