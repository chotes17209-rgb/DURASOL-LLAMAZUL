@php($u = auth()->user())
<x-layouts.app title="Panel de control" :breadcrumb="'Resumen al '.$fecha->translatedFormat('l d \\d\\e F \\d\\e Y')">
    <x-slot:actions>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-xs text-slate-500">Día de venta</label>
            <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" class="form-input w-36" onchange="this.form.submit()">
        </form>
    </x-slot:actions>

    <div class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
        <x-kpi label="Venta del día" :value="soles($ventaDia)" :hint="num($balonesDia).' balones'"/>
        <x-kpi label="Venta acumulada del mes" :value="soles($ventaMes)" :hint="num($balonesMes).' balones'"/>
        @if ($u->hasRole('liquidaciones', 'caja'))
            <x-kpi label="Por cobrar (créditos)" :value="soles($porCobrar)" color="red"/>
        @endif
        @if ($u->hasRole('caja'))
            <x-kpi label="Saldo en caja hoy" :value="soles($saldoCaja)" color="green"/>
        @endif
        @if ($u->hasRole('logistica') && ! $u->hasRole('caja'))
            <x-kpi label="Llenos en almacén (S-10)" :value="num($stock['lleno_s10']['final'])" :hint="'S-45: '.num($stock['lleno_s45']['final']).' · M-10: '.num($stock['lleno_m10']['final'])" color="amber"/>
        @endif
    </div>

    @if ($borradores)
        <div class="help mt-3 flex items-center justify-between">
            <span>Hay <b>{{ $borradores }}</b> liquidación(es) en borrador pendientes de cerrar.</span>
            <a href="{{ route('liquidaciones.index', ['estado' => 'borrador']) }}" class="font-semibold text-brand-700 hover:underline">Revisar</a>
        </div>
    @endif

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><p class="card-title">Venta diaria — últimos 30 días (S/)</p></div>
            <div class="h-64 p-4"><canvas data-chart="{{ json_encode($graficoVentas) }}"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><p class="card-title">Balones vendidos en el mes</p></div>
            <table class="table table-compact">
                <thead><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">Importe</th></tr></thead>
                <tbody>
                @forelse ($porProducto as $p)
                    <tr><td class="font-medium">{{ $p->codigo }}</td><td class="text-right">{{ num($p->cantidad) }}</td><td class="text-right">{{ soles($p->total) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="py-6 text-center text-slate-400">Sin ventas este mes.</td></tr>
                @endforelse
                </tbody>
                @if ($porProducto->isNotEmpty())
                    <tfoot><tr><td>Total</td><td class="text-right">{{ num($porProducto->sum('cantidad')) }}</td><td class="text-right">{{ soles($porProducto->sum('total')) }}</td></tr></tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><p class="card-title">Venta por chofer en el mes</p></div>
            <table class="table table-compact">
                <thead><tr><th>Chofer</th><th class="text-right">Balones</th><th class="text-right">Importe</th></tr></thead>
                <tbody>
                @forelse ($topChoferes as $c)
                    <tr><td class="font-medium">{{ $c->alias }}</td><td class="text-right">{{ num($c->cantidad) }}</td><td class="text-right">{{ soles($c->total) }}</td></tr>
                @empty
                    <tr><td colspan="3" class="py-6 text-center text-slate-400">Sin datos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        @if ($u->hasRole('logistica'))
            <div class="card">
                <div class="card-header">
                    <p class="card-title">Almacén hoy</p>
                    <a href="{{ route('logistica.partes.show', today()->toDateString()) }}" class="text-xs font-semibold text-brand-700 hover:underline">{{ $parteHoy ? 'Ver parte de hoy' : 'Abrir parte de hoy' }}</a>
                </div>
                <table class="table table-compact">
                    <thead><tr><th></th><th class="text-right">Inicial</th><th class="text-right">Ingreso</th><th class="text-right">Salida</th><th class="text-right">Final</th></tr></thead>
                    <tbody>
                    @foreach (['lleno_s10', 'lleno_s45', 'lleno_m10', 'plomo_s10', 'color_s10', 'plomo_s45', 'color_s45'] as $llave)
                        @php($f = $stock[$llave])
                        <tr>
                            <td>{{ $f['tipo'] === 'lleno' ? 'Lleno' : 'Vacío' }} {{ $f['titulo'] }}</td>
                            <td class="text-right">{{ num($f['inicial']) }}</td><td class="text-right">{{ num($f['ingreso']) }}</td><td class="text-right">{{ num($f['salida']) }}</td>
                            <td class="text-right font-semibold">{{ num($f['final']) }}</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            </div>

            <div class="card">
                <div class="card-header"><p class="card-title">Documentos vehiculares</p>@if($docsCriticos)<x-badge color="red">{{ $docsCriticos }} por atender</x-badge>@endif</div>
                <table class="table table-compact">
                    <tbody>
                    @forelse ($alertasDocs as $a)
                        <tr class="cursor-pointer" data-modal-url="{{ route('vehiculos.show', $a['vehiculo']) }}" data-modal-size="xl">
                            <td class="font-mono font-semibold">{{ $a['vehiculo']->placa }}</td>
                            <td>{{ $a['label'] }}</td>
                            <td class="text-xs text-slate-500">{{ $a['documento']?->fecha_vencimiento ? fecha($a['documento']->fecha_vencimiento) : '' }}</td>
                            <td class="text-right"><x-status :value="$a['estado']"/></td>
                        </tr>
                    @empty
                        <tr><td class="py-6 text-center text-emerald-700">Toda la documentación está al día.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        @endif
    </div>
</x-layouts.app>
