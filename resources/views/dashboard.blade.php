@php($u = auth()->user())
<x-layouts.app title="Panel de control" :breadcrumb="'Resumen al '.$fecha->translatedFormat('l d \\d\\e F \\d\\e Y')">
    <x-slot:filters>
        <form method="GET">
            <div class="fb">
                <label>Día de venta</label>
                <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" class="form-input w-44" onchange="this.form.submit()">
            </div>
            <button class="btn btn-primary">Aplicar</button>
        </form>
    </x-slot:filters>

    @php($totalStock = \App\Services\AlmacenService::totalesPorPresentacion($stock))
    <dl class="ledger !grid-cols-2 lg:!grid-cols-5">
        @if ($enSoles)
            <div class="ledger-cell"><dt>Venta del {{ $fecha->format('d/m') }}</dt><dd>{{ soles($ventaDia) }}</dd><p>{{ num($balonesDia) }} balones</p></div>
            <div class="ledger-cell"><dt>Acumulado del mes</dt><dd>{{ soles($ventaMes) }}</dd>
                @if ($utilidadMes !== null)
                    <p><a href="{{ route('reportes.rentabilidad', ['mes' => $fecha->format('Y-m')]) }}" class="hover:text-brand-800 hover:underline">Utilidad bruta {{ soles($utilidadMes) }}{{ $ventaMes > 0 ? ' · '.number_format($utilidadMes / $ventaMes * 100, 1).' %' : '' }}</a></p>
                @else
                    <p>{{ num($balonesMes) }} balones</p>
                @endif
            </div>
        @else
            <div class="ledger-cell"><dt>Balones vendidos el {{ $fecha->format('d/m') }}</dt><dd>{{ num($balonesDia) }}</dd><p>según liquidaciones</p></div>
            <div class="ledger-cell"><dt>Balones vendidos en el mes</dt><dd>{{ num($balonesMes) }}</dd><p>desde el {{ $fecha->copy()->startOfMonth()->format('d/m') }}</p></div>
        @endif
        <div class="ledger-cell"><dt>Stock S-10 (llenos + cambios)</dt><dd>{{ num($totalStock['S10']) }}</dd><p>S-45 {{ num($totalStock['S45']) }} · M-10 {{ num($totalStock['M10']) }}</p></div>
        @if ($u->hasRole('liquidaciones', 'caja'))
            <div class="ledger-cell"><dt>Créditos por cobrar</dt><dd class="!text-red-700">{{ soles($porCobrar) }}</dd><p>saldo pendiente de clientes</p></div>
        @else
            @php($vacios = \App\Services\AlmacenService::totalesVacios($stock))
            <div class="ledger-cell"><dt>Vacíos S-10</dt><dd>{{ num($vacios['S10']) }}</dd><p>S-45 {{ num($vacios['S45']) }}</p></div>
        @endif
        @if ($u->hasRole('caja'))
            <div class="ledger-cell ledger-total"><dt>Saldo en caja hoy</dt><dd>{{ soles($saldoCaja) }}</dd><p>{{ $borradores }} liquidación(es) en borrador</p></div>
        @elseif ($u->hasRole('liquidaciones'))
            <div class="ledger-cell ledger-total"><dt>Liquidaciones por cerrar</dt><dd>{{ $borradores }}</dd><p>en borrador</p></div>
        @else
            <div class="ledger-cell ledger-total"><dt>Parte de hoy</dt><dd>{{ $parteHoy ? ($parteHoy->esEditable() ? 'Abierto' : 'Cerrado') : 'Sin abrir' }}</dd><p><a href="{{ route('logistica.partes.show', today()->toDateString()) }}" class="hover:underline">{{ $parteHoy ? 'Ver parte' : 'Abrir parte' }}</a></p></div>
        @endif
    </dl>

    @if ($borradores && $u->hasRole('liquidaciones', 'caja'))
        <div class="help mt-3 flex items-center justify-between border-amber-300 bg-amber-50 text-amber-900">
            <span>Hay <b>{{ $borradores }}</b> liquidación(es) en borrador pendientes de cerrar.</span>
            <a href="{{ route('liquidaciones.index', ['estado' => 'borrador']) }}" class="font-semibold text-brand-700 hover:underline">Revisar</a>
        </div>
    @endif

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card xl:col-span-2">
            <div class="card-header"><p class="card-title">{{ $enSoles ? 'Venta diaria de los últimos 30 días (S/)' : 'Balones vendidos por día (últimos 30 días)' }}</p></div>
            <div class="h-64 p-4"><canvas data-chart="{{ json_encode($graficoVentas) }}"></canvas></div>
        </div>
        <div class="card">
            <div class="card-header"><p class="card-title">Balones vendidos en el mes</p></div>
            <table class="table table-compact">
                <thead><tr><th>Producto</th><th class="text-right">Cantidad</th>@if ($enSoles)<th class="text-right">Importe</th>@endif</tr></thead>
                <tbody>
                @forelse ($porProducto as $p)
                    <tr><td class="font-medium">{{ $p->codigo }}</td><td class="text-right">{{ num($p->cantidad) }}</td>@if ($enSoles)<td class="text-right">{{ soles($p->total) }}</td>@endif</tr>
                @empty
                    <tr><td colspan="3" class="py-6 text-center text-slate-400">Sin ventas este mes.</td></tr>
                @endforelse
                </tbody>
                @if ($porProducto->isNotEmpty())
                    <tfoot><tr><td>Total</td><td class="text-right">{{ num($porProducto->sum('cantidad')) }}</td>@if ($enSoles)<td class="text-right">{{ soles($porProducto->sum('total')) }}</td>@endif</tr></tfoot>
                @endif
            </table>
        </div>
    </div>

    <div class="mt-4 grid gap-4 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><p class="card-title">{{ $enSoles ? 'Venta por chofer en el mes' : 'Balones por chofer en el mes' }}</p></div>
            <table class="table table-compact">
                <thead><tr><th>Chofer</th><th class="text-right">Balones</th>@if ($enSoles)<th class="text-right">Importe</th>@endif</tr></thead>
                <tbody>
                @forelse ($topChoferes as $c)
                    <tr><td class="font-medium">{{ $c->alias }}</td><td class="text-right">{{ num($c->cantidad) }}</td>@if ($enSoles)<td class="text-right">{{ soles($c->total) }}</td>@endif</tr>
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
                            <td class="whitespace-nowrap">{{ $f['tipo'] === 'lleno' ? 'Lleno' : 'Vacío' }} {{ $f['titulo'] }}</td>
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
                            <td class="whitespace-nowrap font-mono font-semibold">{{ $a['vehiculo']->placa }}</td>
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
