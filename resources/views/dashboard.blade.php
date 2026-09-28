@php($u = auth()->user())
<x-layouts.app title="Panel de control" :breadcrumb="'Hola, '.$u->name">
    <x-slot:actions>
        <form method="GET"><input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" class="form-input w-40" onchange="this.form.submit()"></form>
    </x-slot:actions>

    {{-- Bienvenida con logos --}}
    <div class="relative mb-6 overflow-hidden rounded-3xl bg-gradient-to-r from-brand-900 via-brand-800 to-brand-950 p-6 text-white shadow-xl sm:p-8">
        <div class="pointer-events-none absolute -top-16 -right-16 h-64 w-64 rounded-full bg-orange-500/20 blur-3xl"></div>
        <div class="relative flex flex-col gap-6 lg:flex-row lg:items-center lg:justify-between">
            <div>
                <p class="text-sm font-semibold text-brand-200">Resumen del {{ $fecha->translatedFormat('l d \\d\\e F') }}</p>
                <p class="mt-1 text-3xl font-extrabold tracking-tight">{{ soles($ventaDia) }} <span class="text-lg font-semibold text-brand-200">en {{ num($balonesDia) }} balones</span></p>
                <p class="mt-1 text-sm text-brand-100">Acumulado del mes: <b>{{ soles($ventaMes) }}</b> · {{ num($balonesMes) }} balones</p>
            </div>
            <div class="flex gap-3">
                <div class="rounded-2xl bg-white p-2.5"><img src="{{ asset('img/durasol.jpg') }}" alt="Durasol" class="h-10 w-32 object-contain"></div>
                <div class="rounded-2xl bg-white p-2.5"><img src="{{ asset('img/llamazul.jpg') }}" alt="Llamazul" class="h-10 w-32 object-contain"></div>
            </div>
        </div>
    </div>

    <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @if ($u->hasRole('liquidaciones', 'caja'))
            <x-kpi label="Por cobrar (créditos)" :value="soles($porCobrar)" icon="credit-card" color="red"/>
        @endif
        @if ($u->hasRole('caja'))
            <x-kpi label="Saldo en caja" :value="soles($saldoCaja)" icon="banknotes" color="green"/>
        @endif
        <x-kpi label="Liquidaciones por cerrar" :value="$borradores" icon="clipboard-document-check" color="amber"/>
        @if ($u->hasRole('logistica'))
            <x-kpi label="Llenos en almacén" :value="num(collect($stock['llenos'])->sum('total'))" icon="fire" color="orange" :hint="$enRuta.' chofer(es) en ruta · '.$guiasTransito.' guía(s) en planta'"/>
        @endif
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><p class="card-title">Ventas de los últimos 30 días</p></div>
            <div class="h-72 p-5"><canvas data-chart="{{ json_encode($graficoVentas) }}"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><p class="card-title">Balones vendidos en el mes</p></div>
            <div class="h-72 p-5">
                @if ($porProducto->isEmpty())<x-empty text="Sin ventas este mes."/>@else<canvas data-chart="{{ json_encode($graficoProductos) }}"></canvas>@endif
            </div>
        </div>
    </div>

    <div class="mt-6 grid gap-6 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><p class="card-title">Choferes del mes</p></div>
            <div class="divide-y divide-slate-100">
                @php($max = max(1, (float) $topChoferes->max('total')))
                @forelse ($topChoferes as $c)
                    <div class="px-5 py-3">
                        <div class="flex items-center justify-between text-sm"><span class="font-semibold">{{ $c->alias }}</span><span class="tabular-nums">{{ soles($c->total) }}</span></div>
                        <div class="mt-1.5 flex items-center gap-2">
                            <div class="h-1.5 flex-1 overflow-hidden rounded-full bg-slate-100"><div class="h-full rounded-full bg-brand-500" style="width: {{ round($c->total / $max * 100) }}%"></div></div>
                            <span class="text-[11px] text-slate-400">{{ num($c->cantidad) }} bal.</span>
                        </div>
                    </div>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-slate-400">Sin datos.</p>
                @endforelse
            </div>
        </div>

        @if ($u->hasRole('logistica'))
        <div class="card">
            <div class="card-header"><p class="card-title">Stock actual</p><a href="{{ route('logistica.stock') }}" class="text-xs font-semibold text-brand-600">Ver →</a></div>
            <table class="table table-compact">
                <thead><tr><th>Producto</th>@foreach ($stock['empresas'] as $e)<th class="text-right">{{ $e->nombre }}</th>@endforeach</tr></thead>
                <tbody>
                @foreach ($stock['llenos'] as $fila)
                    <tr><td class="font-mono font-bold">{{ $fila['producto']->codigo }}</td>@foreach ($stock['empresas'] as $e)<td class="text-right tabular-nums">{{ num($fila['empresas'][$e->id]) }}</td>@endforeach</tr>
                @endforeach
                @foreach ($stock['vacios'] as $fila)
                    <tr class="text-slate-500"><td>Vacíos {{ $fila['producto']->capacidad_kg }} kg</td><td colspan="{{ $stock['empresas']->count() }}" class="text-right tabular-nums">{{ num($fila['plomo']) }} plomo · {{ num($fila['color']) }} color</td></tr>
                @endforeach
                </tbody>
            </table>
        </div>

        <div class="card">
            <div class="card-header"><p class="card-title">Documentos vehiculares</p>@if($docsCriticos)<x-badge color="red">{{ $docsCriticos }} por atender</x-badge>@endif</div>
            <div class="divide-y divide-slate-100">
                @forelse ($alertasDocs as $a)
                    <button type="button" class="flex w-full items-center justify-between px-5 py-3 text-left text-sm hover:bg-slate-50" data-modal-url="{{ route('vehiculos.show', $a['vehiculo']) }}" data-modal-size="xl">
                        <span><span class="font-mono font-bold">{{ $a['vehiculo']->placa }}</span> · {{ $a['label'] }}
                            @if ($a['documento']?->fecha_vencimiento)<span class="block text-xs text-slate-400">{{ fecha($a['documento']->fecha_vencimiento) }}</span>@endif</span>
                        <x-status :value="$a['estado']"/>
                    </button>
                @empty
                    <p class="px-5 py-6 text-center text-sm text-emerald-600">Toda la documentación está al día.</p>
                @endforelse
            </div>
        </div>
        @endif
    </div>
</x-layouts.app>
