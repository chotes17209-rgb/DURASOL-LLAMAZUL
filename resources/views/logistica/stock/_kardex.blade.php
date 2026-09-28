<div class="table-wrap">
    <table class="table table-compact">
        <thead><tr><th>Fecha</th><th>Movimiento</th><th>Placa</th><th>Responsable</th><th>Lugar</th><th>Guía</th><th class="text-right">Entrada</th><th class="text-right">Salida</th><th class="text-right">Saldo</th></tr></thead>
        <tbody>
        <tr><td colspan="8" class="font-medium">Saldo inicial al {{ $desde->format('d/m/Y') }}</td><td class="text-right font-semibold">{{ num($kardex['inicial']) }}</td></tr>
        @forelse ($kardex['movimientos'] as $m)
            <tr>
                <td><a class="text-brand-700 hover:underline" href="{{ route('logistica.partes.show', \Illuminate\Support\Carbon::parse($m->fecha_parte)->toDateString()) }}">{{ fecha($m->fecha_parte) }}</a></td>
                <td>{{ str_ends_with($m->bloque, 'ingreso') ? 'Ingreso' : 'Salida' }}</td>
                <td class="font-mono text-xs">{{ $m->placa }}</td><td>{{ $m->responsable }}</td><td>{{ $m->lugar }}</td><td class="text-xs">{{ $m->numero_guia }}</td>
                <td class="text-right text-emerald-700">{{ $m->entrada ? num($m->entrada) : '' }}</td>
                <td class="text-right text-red-700">{{ $m->salida ? num($m->salida) : '' }}</td>
                <td class="text-right font-semibold">{{ num($m->saldo) }}</td>
            </tr>
        @empty
            <tr><td colspan="9" class="py-6 text-center text-slate-400">Sin movimientos en el rango.</td></tr>
        @endforelse
        </tbody>
        <tfoot><tr><td colspan="8">Saldo final al {{ $hasta->format('d/m/Y') }}</td><td class="text-right">{{ num($kardex['final']) }}</td></tr></tfoot>
    </table>
</div>
