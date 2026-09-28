<x-layouts.app title="Hoja de liquidación diaria" breadcrumb="Reportes">
    <x-slot:actions>
        <x-export :url="route('reportes.liquidacion-diaria', ['fecha' => $fecha->toDateString()])"/>
    </x-slot:actions>
    <x-slot:filters>
        <form method="GET">
            <div class="fb">
                <label>Fecha</label>
                <input type="date" name="fecha" value="{{ $fecha->format('Y-m-d') }}" class="form-input w-44" onchange="this.form.submit()">
            </div>
            <button class="btn btn-primary">Aplicar</button>
        </form>
    </x-slot:filters>

    <p class="help mb-3">
        <b>{{ ucfirst($fecha->translatedFormat('l d \\d\\e F \\d\\e Y')) }}</b> · Por depositar = venta + cobranza − crédito − varios − FISE − vouchers − depósitos.
        Reparto local por fecha de venta; ruta por fecha de liquidación.
    </p>

    @foreach ($grupos as $g)
        <div class="card mb-4">
            <div class="card-header"><p class="card-title">{{ $g['titulo'] }}</p><span class="text-xs text-slate-500">{{ count($g['filas']) }} liquidación(es)</span></div>
            <div class="table-wrap">
                <table class="table table-compact table-grid">
                    <thead>
                    <tr>
                        <th>Placa</th><th>Responsable</th>
                        @foreach ($productos as $p)<th class="text-right">{{ $p->codigo }}</th>@endforeach
                        <th class="text-right">Venta total</th><th class="text-right">Cobranza</th><th class="text-right">Crédito</th><th class="text-right">Varios</th>
                        <th class="text-right">FISE</th><th class="text-right">Vouchers</th><th class="text-right">Depósitos</th><th class="text-right">Por depositar</th><th>Estado</th>
                    </tr>
                    </thead>
                    <tbody>
                    @forelse ($g['filas'] as $f)
                        <tr class="cursor-pointer" data-modal-url="{{ route('liquidaciones.show', $f['liquidacion']) }}" data-modal-size="xl" title="Ver liquidación">
                            <td class="font-mono text-xs">{{ $f['placa'] }}</td>
                            <td class="font-semibold">{{ $f['responsable'] }}@if ($f['liquidacion']->tipo === \App\Enums\TipoChofer::Ruta)<span class="ml-1 text-xs font-normal text-slate-500">(venta {{ fecha($f['fecha_venta']) }})</span>@endif</td>
                            @foreach ($f['cantidades'] as $c)<td class="text-right">{{ $c ?: '' }}</td>@endforeach
                            <td class="text-right font-semibold">{{ num($f['venta'], 2) }}</td>
                            <td class="text-right">{{ num($f['cobranza'], 2) }}</td>
                            <td class="text-right">{{ num($f['credito'], 2) }}</td>
                            <td class="text-right">{{ num($f['varios'], 2) }}</td>
                            <td class="text-right">{{ num($f['fise'], 2) }}</td>
                            <td class="text-right">{{ num($f['vouchers'], 2) }}</td>
                            <td class="text-right">{{ num($f['depositos'], 2) }}</td>
                            <td class="text-right font-semibold">{{ num($f['por_depositar'], 2) }}</td>
                            <td><x-status :value="$f['liquidacion']->estado"/></td>
                        </tr>
                    @empty
                        <tr><td colspan="{{ 11 + $productos->count() }}" class="py-5 text-center text-slate-400">Sin liquidaciones para esta fecha.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($g['filas'])
                        @php($t = $g['total'])
                        <tfoot>
                        <tr>
                            <td colspan="2">TOTALES</td>
                            @foreach ($t['cantidades'] as $c)<td class="text-right">{{ $c ?: '' }}</td>@endforeach
                            <td class="text-right">{{ num($t['venta'], 2) }}</td><td class="text-right">{{ num($t['cobranza'], 2) }}</td><td class="text-right">{{ num($t['credito'], 2) }}</td>
                            <td class="text-right">{{ num($t['varios'], 2) }}</td><td class="text-right">{{ num($t['fise'], 2) }}</td><td class="text-right">{{ num($t['vouchers'], 2) }}</td><td class="text-right">{{ num($t['depositos'], 2) }}</td>
                            <td class="text-right">{{ num($t['por_depositar'], 2) }}</td><td></td>
                        </tr>
                        </tfoot>
                    @endif
                </table>
            </div>
        </div>
    @endforeach

    <div class="grid gap-4 xl:grid-cols-3">
        <div class="card">
            <div class="card-header"><p class="card-title">Por depositar</p></div>
            <table class="table table-compact">
                <tbody>
                @forelse ($porDepositar as $responsable => $monto)
                    <tr><td>{{ $responsable }}</td><td class="text-right">{{ num($monto, 2) }}</td></tr>
                @empty
                    <tr><td class="py-5 text-center text-slate-400">—</td></tr>
                @endforelse
                </tbody>
                <tfoot><tr><td>TOTAL</td><td class="text-right">{{ num(array_sum($porDepositar), 2) }}</td></tr></tfoot>
            </table>
        </div>

        <div class="card">
            <div class="card-header"><p class="card-title">Depósitos (−)</p></div>
            <table class="table table-compact">
                <thead><tr><th>Responsable</th><th>Cuenta / destino</th><th>Detalle</th><th class="text-right">Importe</th></tr></thead>
                <tbody>
                @forelse ($depositos as $d)
                    <tr><td>{{ $d['responsable'] }}</td><td class="font-medium">{{ $d['destino'] }}</td><td class="text-xs text-slate-500">{{ $d['detalle'] }}</td><td class="text-right">{{ num($d['monto'], 2) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="py-5 text-center text-slate-400">Sin depósitos este día.</td></tr>
                @endforelse
                </tbody>
                <tfoot><tr><td colspan="3">TOTAL</td><td class="text-right">{{ num($depositos->sum('monto'), 2) }}</td></tr></tfoot>
            </table>
            <p class="border-t border-line px-3 py-2 text-xs text-slate-500">Otros egresos de caja del día: <b class="text-slate-800">{{ soles($gastosCaja) }}</b></p>
        </div>

        <div class="card">
            <div class="card-header"><p class="card-title">Detalle de ventas por precio</p></div>
            <div class="max-h-[28rem] overflow-y-auto">
                <table class="table table-compact">
                    <thead class="sticky top-0"><tr><th>Producto</th><th class="text-right">Cant.</th><th class="text-right">P.U.</th><th class="text-right">Total</th></tr></thead>
                    <tbody>
                    @forelse ($detallePrecios as $d)
                        <tr><td>{{ $d->producto?->codigo }}</td><td class="text-right">{{ num($d->cantidad) }}</td><td class="text-right">{{ num($d->precio, 2) }}</td><td class="text-right">{{ num($d->total, 2) }}</td></tr>
                    @empty
                        <tr><td colspan="4" class="py-5 text-center text-slate-400">Sin ventas.</td></tr>
                    @endforelse
                    </tbody>
                    @if ($detallePrecios->isNotEmpty())
                        <tfoot><tr><td>TOTAL</td><td class="text-right">{{ num($detallePrecios->sum('cantidad')) }}</td><td></td><td class="text-right">{{ num($detallePrecios->sum('total'), 2) }}</td></tr></tfoot>
                    @endif
                </table>
            </div>
        </div>
    </div>
</x-layouts.app>
