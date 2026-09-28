@php($d = (float) $arqueo->diferencia)
<x-modal :title="'Arqueo · '.$arqueo->nombreCaja().' · '.fecha($arqueo->fecha)" :subtitle="'Registrado por '.($arqueo->user?->name ?? '—').' el '.$arqueo->updated_at->format('d/m/Y H:i')">
    <div class="grid gap-4 sm:grid-cols-2">
        @foreach (config('erp.denominaciones') as $tipo => $valores)
            @php($subtotal = 0)
            <table class="table table-compact table-grid">
                <thead><tr><th>{{ ucfirst($tipo) }}</th><th class="text-right">Cant.</th><th class="text-right">Importe</th></tr></thead>
                <tbody>
                @foreach ($valores as $v)
                    @php($n = (int) ($arqueo->detalle[\App\Services\CajaChicaService::clave($v)] ?? 0))
                    @php($subtotal += $n * $v)
                    <tr><td>S/ {{ number_format($v, 2) }}</td><td class="text-right">{{ $n ?: '' }}</td><td class="text-right">{{ $n ? num($n * $v, 2) : '' }}</td></tr>
                @endforeach
                </tbody>
                <tfoot><tr><td colspan="2">Subtotal</td><td class="text-right">{{ num($subtotal, 2) }}</td></tr></tfoot>
            </table>
        @endforeach
    </div>
    <table class="recibo mt-4 border border-line">
        <tr class="subtotal"><td>Total contado</td><td>{{ num($arqueo->total_contado, 2) }}</td></tr>
        <tr><td>Saldo según sistema</td><td>{{ num($arqueo->saldo_sistema, 2) }}</td></tr>
        <tr class="final"><td>{{ abs($d) < 0.005 ? 'Cuadra' : ($d > 0 ? 'Sobrante' : 'Faltante') }}</td><td>S/ {{ num(abs($d), 2) }}</td></tr>
    </table>
    @if ($arqueo->observaciones)<p class="help mt-3">{{ $arqueo->observaciones }}</p>@endif
    <x-slot:footer>
        <a href="{{ route('caja.arqueos.show', [$arqueo, 'formato' => 'pdf']) }}" class="btn btn-secondary"><x-heroicon-o-document-arrow-down/> PDF</a>
        <a href="{{ route('caja.arqueos.index', ['fecha' => $arqueo->fecha->format('Y-m-d'), 'caja' => $arqueo->caja]) }}" class="btn btn-primary">Abrir arqueo</a>
    </x-slot:footer>
</x-modal>
