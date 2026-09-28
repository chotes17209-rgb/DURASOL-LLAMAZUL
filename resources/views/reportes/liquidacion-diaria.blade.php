@php
    $gas = $productos->filter(fn ($p) => in_array($p->tipo, ['gas', 'envase']));
    $fila = function ($l) use ($gas) {
        $porProducto = $l->items->groupBy('producto_id')->map->sum('cantidad');
        return ['l' => $l, 'productos' => $gas->mapWithKeys(fn ($p) => [$p->id => (int) ($porProducto[$p->id] ?? 0)])];
    };
    $totalizar = fn ($grupo) => [
        'productos' => $gas->mapWithKeys(fn ($p) => [$p->id => $grupo->sum(fn ($l) => $l->items->where('producto_id', $p->id)->sum('cantidad'))]),
        'venta' => $grupo->sum('total_venta'), 'cobranza' => $grupo->sum('total_cobranzas'), 'credito' => $grupo->sum('total_credito'),
        'gastos' => $grupo->sum('total_gastos'), 'fise' => $grupo->sum('total_fises'), 'vouchers' => $grupo->sum('total_vouchers'), 'efectivo' => $grupo->sum('efectivo_esperado'),
        'entregado' => $grupo->sum(fn ($l) => (float) ($l->efectivo_entregado ?? 0)),
    ];
@endphp
<x-layouts.app title="Hoja de liquidación diaria" breadcrumb="Reportes">
    <x-slot:actions>
        <form method="GET" class="flex items-center gap-2">
            <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" class="form-input w-40" onchange="this.form.submit()">
        </form>
        <button class="btn btn-secondary" onclick="window.print()"><x-heroicon-o-printer class="h-4 w-4"/> Imprimir</button>
    </x-slot:actions>

    <p class="mb-4 text-sm text-slate-500">Ventas del <b class="text-slate-900">{{ $fecha->translatedFormat('l d \\d\\e F Y') }}</b>. Efectivo = venta + cobranza − crédito − vouchers − FISE − gastos.</p>

    @foreach (['Reparto local y almacén' => $locales, 'Choferes en ruta' => $ruta] as $titulo => $grupo)
        <div class="card mb-6">
            <div class="card-header"><p class="card-title">{{ $titulo }}</p><x-badge color="slate">{{ $grupo->count() }} liquidaciones</x-badge></div>
            @if ($grupo->isEmpty())
                <x-empty text="Sin liquidaciones para esta fecha."/>
            @else
            @php($t = $totalizar($grupo))
            <div class="table-wrap">
                <table class="table table-compact">
                    <thead><tr><th>Placa</th><th>Responsable</th>@foreach ($gas as $p)<th class="text-right">{{ $p->codigo }}</th>@endforeach
                        <th class="text-right">Venta total</th><th class="text-right">Cobranza</th><th class="text-right">Crédito</th><th class="text-right">Gastos</th><th class="text-right">FISE</th><th class="text-right">Vouchers</th><th class="text-right">Por depositar</th><th class="text-right">Entregado</th><th>Estado</th></tr></thead>
                    <tbody>
                    @foreach ($grupo->map($fila) as $f)
                        <tr class="cursor-pointer" data-modal-url="{{ route('liquidaciones.show', $f['l']) }}" data-modal-size="xl">
                            <td class="text-xs">{{ $f['l']->vehiculo?->placa ?? 'LOCAL' }}</td>
                            <td class="font-bold">{{ $f['l']->chofer?->alias }}</td>
                            @foreach ($gas as $p)<td class="text-right tabular-nums {{ $f['productos'][$p->id] ? '' : 'text-slate-300' }}">{{ $f['productos'][$p->id] }}</td>@endforeach
                            <td class="text-right font-semibold tabular-nums">{{ num($f['l']->total_venta, 2) }}</td>
                            <td class="text-right tabular-nums">{{ num($f['l']->total_cobranzas, 2) }}</td>
                            <td class="text-right tabular-nums text-rose-600">{{ num($f['l']->total_credito, 2) }}</td>
                            <td class="text-right tabular-nums">{{ num($f['l']->total_gastos, 2) }}</td>
                            <td class="text-right tabular-nums text-violet-600">{{ num($f['l']->total_fises, 2) }}</td>
                            <td class="text-right tabular-nums">{{ num($f['l']->total_vouchers, 2) }}</td>
                            <td class="text-right font-bold tabular-nums">{{ num($f['l']->efectivo_esperado, 2) }}</td>
                            <td class="text-right tabular-nums">{{ $f['l']->efectivo_entregado !== null ? num($f['l']->efectivo_entregado, 2) : '—' }}</td>
                            <td><x-status :value="$f['l']->estado"/></td>
                        </tr>
                    @endforeach
                    </tbody>
                    <tfoot><tr><td colspan="2">TOTALES</td>@foreach ($gas as $p)<td class="text-right">{{ $t['productos'][$p->id] }}</td>@endforeach
                        <td class="text-right">{{ num($t['venta'], 2) }}</td><td class="text-right">{{ num($t['cobranza'], 2) }}</td><td class="text-right">{{ num($t['credito'], 2) }}</td><td class="text-right">{{ num($t['gastos'], 2) }}</td>
                        <td class="text-right">{{ num($t['fise'], 2) }}</td><td class="text-right">{{ num($t['vouchers'], 2) }}</td><td class="text-right">{{ num($t['efectivo'], 2) }}</td><td class="text-right">{{ num($t['entregado'], 2) }}</td><td></td></tr></tfoot>
                </table>
            </div>
            @endif
        </div>
    @endforeach

    <div class="grid gap-6 xl:grid-cols-2">
        <div class="card">
            <div class="card-header"><p class="card-title">Detalle de ventas por precio</p></div>
            <div class="max-h-[32rem] overflow-y-auto">
                <table class="table table-compact">
                    <thead class="sticky top-0"><tr><th>Producto</th><th class="text-right">Cantidad</th><th class="text-right">P.U.</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                    @forelse ($detallePrecios as $d)
                        <tr><td class="font-mono">{{ $d->producto?->codigo }}</td><td class="text-right">{{ num($d->cantidad) }}</td><td class="text-right">{{ num($d->precio, 2) }}</td><td class="text-right">{{ soles($d->total) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="text-center text-slate-400">Sin ventas.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($detallePrecios->isNotEmpty())
                        <tfoot><tr><td>Total</td><td class="text-right">{{ num($detallePrecios->sum('cantidad')) }}</td><td></td><td class="text-right">{{ soles($detallePrecios->sum('total')) }}</td></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>
        <div class="card">
            <div class="card-header"><p class="card-title">Depósitos realizados</p><span class="text-sm font-bold">{{ soles($depositos->sum('monto')) }}</span></div>
            <table class="table table-compact">
                <thead><tr><th>Cuenta</th><th>Responsable</th><th>Quién</th><th class="text-right">Importe</th></tr></thead>
                <tbody>
                @forelse ($depositos as $d)
                    <tr><td>{{ $d->cuentaBancaria?->nombreMostrar() }}</td><td>{{ $d->chofer?->alias }}</td><td>{{ $d->depositante }}</td><td class="text-right">{{ soles($d->monto) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-400">Sin depósitos este día.</td></tr>
                @endforelse
                </tbody>
            </table>
            <p class="border-t border-slate-100 px-5 py-3 text-sm text-slate-500">Otros egresos de caja del día: <b class="text-slate-800">{{ soles($gastosCaja) }}</b></p>
        </div>
    </div>
</x-layouts.app>
