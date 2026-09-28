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
<body class="min-h-screen" x-data="{ sidebar: false }">
@php($menu = \App\Support\Menu::para(auth()->user()))

{{-- Fondo para cerrar el menú en celulares --}}
<div x-show="sidebar" x-transition.opacity x-cloak class="fixed inset-0 z-30 bg-slate-900/50 backdrop-blur-sm lg:hidden" @click="sidebar = false"></div>

{{-- Menú lateral --}}
<aside class="fixed inset-y-0 left-0 z-40 flex w-68 flex-col bg-gradient-to-b from-brand-950 via-[#131a4a] to-slate-950 transition-transform duration-300 lg:translate-x-0"
       :class="sidebar ? 'translate-x-0' : '-translate-x-full'" style="width: 17rem">
    <div class="flex items-center gap-3 px-5 pt-5 pb-4">
        <div class="flex h-10 w-10 items-center justify-center rounded-2xl bg-gradient-to-br from-flame-500 to-rose-600 shadow-lg shadow-orange-500/30">
            <x-heroicon-s-fire class="h-6 w-6 text-white"/>
        </div>
        <div class="leading-tight">
            <p class="text-sm font-extrabold tracking-tight text-white">Durasol · Llamazul</p>
            <p class="text-[11px] font-medium text-slate-400">ERP de distribución de gas</p>
        </div>
    </div>

    <nav class="flex-1 overflow-y-auto px-3 pb-6 [scrollbar-width:thin]">
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

    <div class="m-3 rounded-2xl bg-white/5 p-3 ring-1 ring-white/10">
        <div class="flex items-center gap-3">
            <div class="flex h-9 w-9 items-center justify-center rounded-xl bg-brand-500 text-xs font-bold text-white">{{ auth()->user()->initials() }}</div>
            <div class="min-w-0 flex-1">
                <p class="truncate text-sm font-semibold text-white">{{ auth()->user()->name }}</p>
                <p class="truncate text-[11px] text-slate-400">{{ auth()->user()->role->label() }}</p>
            </div>
            <form method="POST" action="{{ route('logout') }}" id="logout-form">@csrf</form>
            <button type="button" title="Cerrar sesión" class="rounded-lg p-2 text-slate-400 transition hover:bg-white/10 hover:text-white"
                    onclick="confirmAction({title: '¿Cerrar sesión?', confirmText: 'Sí, salir'}).then(ok => ok && document.getElementById('logout-form').submit())">
                <x-heroicon-o-arrow-right-start-on-rectangle class="h-5 w-5"/>
            </button>
        </div>
    </div>
</aside>

<div class="lg:pl-[17rem]">
    {{-- Barra superior --}}
    <header class="sticky top-0 z-20 border-b border-slate-200/70 bg-white/80 backdrop-blur-xl">
        <div class="flex h-16 items-center gap-4 px-4 sm:px-6 lg:px-8">
            <button class="btn-icon lg:hidden" @click="sidebar = true"><x-heroicon-o-bars-3 class="h-5 w-5"/></button>
            <div class="min-w-0 flex-1">
                @isset($breadcrumb)
                    <p class="truncate text-xs font-medium text-slate-400">{{ $breadcrumb }}</p>
                @endisset
                <h1 class="truncate text-lg font-bold tracking-tight text-slate-900">{{ $title ?? 'Panel' }}</h1>
            </div>
            <div class="hidden items-center gap-2 text-xs font-medium text-slate-500 sm:flex">
                <x-heroicon-o-calendar class="h-4 w-4"/>
                {{ now()->translatedFormat('l d \\d\\e F, Y') }}
            </div>
            @isset($actions)
                <div class="flex items-center gap-2">{{ $actions }}</div>
            @endisset
        </div>
    </header>

    <main class="px-4 py-6 sm:px-6 lg:px-8">
        {{ $slot }}
    </main>
</div>

{{-- Modales apilados cargados por AJAX --}}
<template x-for="(m, index) in $store.modal.stack" :key="m.id">
    <div class="fixed inset-0 flex items-start justify-center overflow-y-auto p-4 sm:p-8" :style="`z-index: ${50 + index * 5}`" :data-modal-id="m.id"
         @keydown.escape.window="index === $store.modal.stack.length - 1 && $store.modal.close()">
        <div class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm" @click="$store.modal.close()" x-transition.opacity></div>
        <div class="relative my-auto w-full rounded-3xl bg-white shadow-2xl ring-1 ring-slate-900/5"
             x-transition:enter="transition ease-out duration-200" x-transition:enter-start="opacity-0 translate-y-4 scale-95" x-transition:enter-end="opacity-100 translate-y-0 scale-100"
             :class="{ 'max-w-md': m.size === 'sm', 'max-w-2xl': m.size === 'md', 'max-w-4xl': m.size === 'lg', 'max-w-6xl': m.size === 'xl', 'max-w-[96rem]': m.size === 'full' }">
            <div x-show="m.loading" class="flex items-center justify-center gap-3 p-16 text-sm text-slate-500">
                <span class="inline-block h-6 w-6 animate-spin rounded-full border-2 border-brand-200 border-t-brand-600"></span> Cargando...
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
