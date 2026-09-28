@php
    $totalLlenos = collect($resumen['llenos'])->sum('total');
    $totalVacios = collect($resumen['vacios'])->sum('plomo');
    $totalColores = collect($resumen['vacios'])->sum('color');
    $totalCambios = collect($resumen['llenos'])->sum('total_cambios');
    $enCamino = $choferesEnRuta->sum(fn ($d) => $d->totalSalida());
@endphp
<x-layouts.app title="Stock y kardex" breadcrumb="Logística · Movimiento de masa">
    <x-slot:actions>
        <form method="GET" class="flex items-center gap-2">
            <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" class="form-input w-40" onchange="this.form.submit()">
        </form>
        <a href="{{ route('logistica.stock.kardex') }}" class="btn btn-primary"><x-heroicon-o-queue-list class="h-4 w-4"/> Kardex</a>
    </x-slot:actions>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-5">
        <x-kpi label="Llenos en almacén" :value="num($totalLlenos)" icon="fire" color="orange"/>
        <x-kpi label="Vacíos plomo" :value="num($totalVacios)" icon="cube-transparent" color="slate"/>
        <x-kpi label="Vacíos de color" :value="num($totalColores)" icon="swatch" color="violet" hint="Para canje"/>
        <x-kpi label="Cambios (fallados)" :value="num($totalCambios)" icon="exclamation-triangle" color="amber" hint="Por devolver a planta"/>
        <x-kpi label="Con choferes en ruta" :value="num($enCamino)" icon="truck" color="sky" :hint="$choferesEnRuta->count().' despacho(s) sin retornar'"/>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><p class="card-title">Balones llenos por empresa <span class="font-normal text-slate-400">al {{ fecha($fecha) }}</span></p></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Producto</th>@foreach ($resumen['empresas'] as $e)<th class="text-right">{{ $e->nombre }}</th>@endforeach<th class="text-right">Total llenos</th><th class="text-right">Cambios</th></tr></thead>
                    <tbody>
                    @foreach ($resumen['llenos'] as $fila)
                        <tr>
                            <td><span class="font-mono font-bold">{{ $fila['producto']->codigo }}</span> <span class="text-xs text-slate-500">{{ $fila['producto']->nombre }}</span></td>
                            @foreach ($resumen['empresas'] as $e)
                                <td class="text-right tabular-nums {{ $fila['empresas'][$e->id] < 0 ? 'font-bold text-rose-600' : '' }}">{{ num($fila['empresas'][$e->id]) }}</td>
                            @endforeach
                            <td class="text-right text-base font-bold tabular-nums">{{ num($fila['total']) }}</td>
                            <td class="text-right tabular-nums text-amber-600">{{ num($fila['total_cambios']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><p class="card-title">Vacíos</p></div>
            <div class="table-wrap">
                <table class="table">
                    <thead><tr><th>Envase</th><th class="text-right">Plomo</th><th class="text-right">Color</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                    @foreach ($resumen['vacios'] as $fila)
                        <tr><td class="font-semibold">{{ $fila['producto']->capacidad_kg }} kg</td><td class="text-right tabular-nums">{{ num($fila['plomo']) }}</td>
                            <td class="text-right tabular-nums text-violet-600">{{ num($fila['color']) }}</td><td class="text-right font-bold tabular-nums">{{ num($fila['total']) }}</td></tr>
                    @endforeach
                    </tbody>
                </table>
            </div>
            <div class="grid grid-cols-2 gap-3 border-t border-slate-100 p-5 text-sm">
                @foreach (['lleno' => 'Llenos', 'vacio' => 'Vacíos', 'color' => 'Colores', 'cambio' => 'Cambios'] as $estado => $label)
                    <div class="rounded-xl bg-slate-50 p-3">
                        <p class="text-xs font-semibold text-slate-500">{{ $label }} hoy</p>
                        <p class="mt-1 tabular-nums"><span class="text-emerald-600">+{{ num($movDia[$estado]->entradas ?? 0) }}</span> · <span class="text-rose-600">−{{ num($movDia[$estado]->salidas ?? 0) }}</span></p>
                    </div>
                @endforeach
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="card-header"><p class="card-title">Guías en tránsito (camiones en planta)</p><a href="{{ route('logistica.guias.index') }}" class="text-xs font-semibold text-brand-600">Ver guías →</a></div>
            <div class="divide-y divide-slate-100">
                @forelse ($guiasTransito as $g)
                    <div class="flex items-center justify-between px-5 py-3 text-sm">
                        <div><p class="font-semibold">{{ $g->numero_guia }} · {{ $g->empresa->nombre }}</p><p class="text-xs text-slate-500">Salió {{ fecha($g->fecha_salida) }} con {{ $g->totalEnviado() }} balones</p></div>
                        <button class="btn btn-success btn-sm" data-modal-url="{{ route('logistica.guias.recibir', $g) }}" data-modal-size="xl">Recibir</button>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-slate-400">No hay camiones en planta.</p>
                @endforelse
            </div>
        </div>
        <div class="card">
            <div class="card-header"><p class="card-title">Últimos movimientos</p><a href="{{ route('logistica.stock.kardex') }}" class="text-xs font-semibold text-brand-600">Kardex completo →</a></div>
            <div class="max-h-96 overflow-y-auto">@include('logistica.stock._movimientos', ['movimientos' => $ultimos])</div>
        </div>
    </div>
</x-layouts.app>
