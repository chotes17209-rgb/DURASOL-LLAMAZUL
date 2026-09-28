@php($t = fn ($k) => array_sum(array_column($dias, $k)))
<x-layouts.app title="Caja por día" breadcrumb="Reportes">
    <x-slot:actions>
        <form method="GET" class="flex items-center gap-2">
            <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="form-input w-40">
            <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="form-input w-40">
            <button class="btn btn-primary">Ver</button>
        </form>
        <button class="btn btn-secondary" onclick="window.print()"><x-heroicon-o-printer class="h-4 w-4"/></button>
    </x-slot:actions>
    <p class="mb-4 text-sm text-slate-500">General = venta + cobranza − crédito − gastos − FISE. Saldo = general − depósitos (igual que la hoja «CAJA GNRAL» del Excel).</p>
    <div class="card">
        <div class="table-wrap">
            <table class="table table-compact">
                <thead><tr><th>Fecha</th><th class="text-right">Balones</th><th class="text-right">Importe total</th><th class="text-right">Cobranza</th><th class="text-right">Crédito</th><th class="text-right">Gastos</th><th class="text-right">FISE</th><th class="text-right">Vouchers</th><th class="text-right">General</th><th class="text-right">Depósitos</th><th class="text-right">Saldo</th></tr></thead>
                <tbody>
                @foreach ($dias as $d)
                    <tr @class(['text-slate-300' => $d['venta'] == 0 && $d['depositos'] == 0])>
                        <td class="whitespace-nowrap">{{ $d['fecha']->translatedFormat('D d/m') }}</td>
                        <td class="text-right tabular-nums">{{ num($d['balones']) }}</td>
                        <td class="text-right tabular-nums font-semibold">{{ num($d['venta'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ num($d['cobranza'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ num($d['credito'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ num($d['gastos'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ num($d['fise'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ num($d['vouchers'], 2) }}</td>
                        <td class="text-right tabular-nums font-semibold">{{ num($d['general'], 2) }}</td>
                        <td class="text-right tabular-nums">{{ num($d['depositos'], 2) }}</td>
                        <td class="text-right tabular-nums font-bold {{ $d['saldo'] < 0 ? 'text-rose-600' : '' }}">{{ num($d['saldo'], 2) }}</td>
                    </tr>
                @endforeach
                </tbody>
                <tfoot><tr><td>TOTAL</td><td class="text-right">{{ num($t('balones')) }}</td><td class="text-right">{{ num($t('venta'), 2) }}</td><td class="text-right">{{ num($t('cobranza'), 2) }}</td><td class="text-right">{{ num($t('credito'), 2) }}</td>
                    <td class="text-right">{{ num($t('gastos'), 2) }}</td><td class="text-right">{{ num($t('fise'), 2) }}</td><td class="text-right">{{ num($t('vouchers'), 2) }}</td><td class="text-right">{{ num($t('general'), 2) }}</td><td class="text-right">{{ num($t('depositos'), 2) }}</td><td class="text-right">{{ num($t('saldo'), 2) }}</td></tr></tfoot>
            </table>
        </div>
    </div>
</x-layouts.app>
