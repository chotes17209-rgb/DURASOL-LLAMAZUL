<div class="table-wrap">
    <table class="table table-grid table-compact">
        <thead>
        <tr>
            <th class="w-10 text-center">Ítem</th><th>Fecha</th><th>Concepto</th><th class="min-w-56">Descripción</th>
            <th class="text-right">Monto</th><th class="text-right">Saldo caja</th><th>N° comprobante</th><th class="min-w-52">Proveedor</th><th>RUC</th>
            <th>Vehículo</th><th class="min-w-36">Conductor</th><th class="min-w-44">Observación</th><th class="w-20"></th>
        </tr>
        </thead>
        <tbody>
        <tr class="bg-panel">
            <td></td><td></td><td colspan="2" class="font-semibold text-slate-900">SALDO INICIAL</td><td></td>
            <td class="text-right font-semibold">{{ num($resumen['saldo_inicial'], 2) }}</td><td colspan="7"></td>
        </tr>
        @forelse ($resumen['filas'] as $i => $f)
            @php($m = $f['movimiento'])
            <tr>
                <td class="text-center text-slate-500">{{ $i + 1 }}</td>
                <td class="whitespace-nowrap">{{ $m->fecha->format('d/m/y') }}</td>
                <td class="whitespace-nowrap">@if($m->esReposicion())<x-badge color="green">Reposición</x-badge>@else{{ $m->concepto }}@endif</td>
                <td class="text-[12px]">{{ $m->descripcion }}</td>
                <td class="text-right {{ $m->esReposicion() ? 'text-emerald-700' : 'text-red-700' }}">{{ $m->esReposicion() ? '' : '−' }}{{ num($m->monto, 2) }}</td>
                <td class="text-right font-medium">{{ num($f['saldo'], 2) }}</td>
                <td class="whitespace-nowrap font-mono text-[12px]">{{ $m->comprobante }}</td>
                <td class="text-[12px]">{{ $m->proveedor }}</td>
                <td class="font-mono text-[12px]">{{ $m->ruc }}</td>
                <td class="whitespace-nowrap">{{ $m->vehiculo?->placa }}</td>
                <td class="text-[12px]">{{ $m->chofer?->nombre_completo ?: $m->chofer?->alias }}</td>
                <td class="text-[12px] text-slate-600">{{ $m->observacion }}</td>
                <td><x-row-actions size="lg" :show="route('caja.chica.show', $m)" :edit="route('caja.chica.edit', $m)" :delete="route('caja.chica.destroy', $m)"/></td>
            </tr>
        @empty
            <tr><td colspan="13"><x-empty text="No hay movimientos de caja chica en el periodo."/></td></tr>
        @endforelse
        </tbody>
        <tfoot>
        <tr><td colspan="4">Total gasto</td><td class="text-right">{{ num($resumen['gastos'], 2) }}</td><td colspan="8"></td></tr>
        <tr><td colspan="4">Saldo final caja</td><td></td><td class="text-right">S/ {{ num($resumen['saldo_final'], 2) }}</td><td colspan="7"></td></tr>
        </tfoot>
    </table>
</div>
