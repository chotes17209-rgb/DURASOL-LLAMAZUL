@php($u = auth()->user())
<x-layouts.app title="Panel de control" :breadcrumb="'Resumen al '.$fecha->translatedFormat('l d \\d\\e F \\d\\e Y')">
    <x-slot:actions>
        <form method="GET" class="flex items-center gap-2">
            <label class="text-xs text-slate-500">Día de venta</label>
            <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" class="form-input w-36" onchange="this.form.submit()">
        </form>
    </x-slot:actions>

    <dl class="ledger !grid-cols-2 lg:!grid-cols-5">
        <div class="ledger-cell"><dt>Venta del {{ $fecha->format('d/m') }}</dt><dd>{{ soles($ventaDia) }}</dd><p class="text-[12px] text-slate-500">{{ num($balonesDia) }} balones</p></div>
        <div class="ledger-cell"><dt>Acumulado del mes</dt><dd>{{ soles($ventaMes) }}</dd>
            @if ($utilidadMes !== null)
                <p><a href="{{ route('reportes.rentabilidad', ['mes' => $fecha->format('Y-m')]) }}" class="hover:text-brand-800 hover:underline">Utilidad bruta {{ soles($utilidadMes) }}{{ $ventaMes > 0 ? ' · '.number_format($utilidadMes / $ventaMes * 100, 1).' %' : '' }}</a></p>
            @else
                <p>{{ num($balonesMes) }} balones</p>
            @endif
        </div>
        @php($totalStock = \App\Services\AlmacenService::totalesPorPresentacion($stock))
        <div class="ledger-cell"><dt>Stock S-10 (llenos + cambios)</dt><dd>{{ num($totalStock['S10']) }}</dd><p class="text-[12px] text-slate-500">S-45 {{ num($totalStock['S45']) }} · M-10 {{ num($totalStock['M10']) }}</p></div>
        @if ($u->hasRole('liquidaciones', 'caja'))
            <div class="ledger-cell"><dt>Créditos por cobrar</dt><dd class="!text-red-700">{{ soles($porCobrar) }}</dd><p class="text-[12px] text-slate-500">saldo pendiente de clientes</p></div>
        @endif
        <div class="ledger-cell ledger-total border-r-0"><dt>{{ $u->hasRole('caja') ? 'Saldo en caja hoy' : 'Liquidaciones por cerrar' }}</dt><dd>{{ $u->hasRole('caja') ? soles($saldoCaja) : $borradores }}</dd><p>{{ $borradores }} en borrador</p></div>
    </dl>

    @if ($borradores)
        <div class="help mt-3 flex items-center justify-between border-amber-300 bg-amber-50 text-amber-900">
            <span>Hay <b>{{ $borradores }}</b> liquidación(es) en borrador pendientes de cerrar.</span>
            <a href="{{ route('liquidaciones.index', ['estado' => 'borrador']) }}" class="font-semibold text-brand-700 hover:underline">Revisar</a>
        </div>
    @endif

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><p class="card-title">Venta diaria de los últimos 30 días (S/)</p></div>
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
