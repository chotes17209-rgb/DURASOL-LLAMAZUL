<div class="grid gap-4 border-b border-slate-100 p-5 sm:grid-cols-3">
    <div class="rounded-2xl bg-slate-50 p-4"><p class="kpi-label">Saldo inicial</p><p class="kpi-value">{{ num($kardex['saldo_inicial']) }}</p></div>
    <div class="rounded-2xl bg-slate-50 p-4"><p class="kpi-label">Movimientos</p><p class="kpi-value">{{ $kardex['movimientos']->count() }}</p></div>
    <div class="rounded-2xl bg-brand-50 p-4"><p class="kpi-label">Saldo final</p><p class="kpi-value text-brand-700">{{ num($kardex['saldo_final']) }}</p></div>
</div>
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Fecha</th><th>Concepto</th><th>Documento</th><th>Usuario</th><th class="text-right">Entrada</th><th class="text-right">Salida</th><th class="text-right">Saldo</th></tr></thead>
        <tbody>
        @forelse ($kardex['movimientos'] as $m)
            <tr>
                <td>{{ fecha($m->fecha) }}</td>
                <td>{{ $m->concepto }}</td>
                <td class="text-xs">{{ config('erp.modelos.'.$m->origen_type, $m->origen_type) }}</td>
                <td class="text-xs">{{ $m->user?->name ?? 'Importado' }}</td>
                <td class="text-right tabular-nums text-emerald-600">{{ $m->cantidad > 0 ? num($m->cantidad) : '' }}</td>
                <td class="text-right tabular-nums text-rose-600">{{ $m->cantidad < 0 ? num(-$m->cantidad) : '' }}</td>
                <td class="text-right font-semibold tabular-nums">{{ num($m->saldo) }}</td>
            </tr>
        @empty
            <tr><td colspan="7"><x-empty text="No hubo movimientos en el rango seleccionado."/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
