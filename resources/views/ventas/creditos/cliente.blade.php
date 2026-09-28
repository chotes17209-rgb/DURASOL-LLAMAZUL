<x-modal :title="'Cuenta corriente · '.$cliente->nombre" :subtitle="'Deuda pendiente: '.soles($cliente->deudaPendiente())" icon="credit-card">
    <div x-data="{ tab: 'creditos' }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <x-tabs :tabs="['creditos' => 'Créditos', 'cobranzas' => 'Cobranzas']" class="mb-0"/>
            <button class="btn btn-success btn-sm" data-modal-url="{{ route('creditos.cobranzas.create', ['cliente_id' => $cliente->id]) }}" data-modal-size="md"><x-heroicon-o-banknotes class="h-4 w-4"/> Registrar cobranza</button>
        </div>
        <div class="mt-5" x-show="tab === 'creditos'">
            <table class="table table-compact">
                <thead><tr><th>Fecha</th><th>Origen</th><th class="text-right">Monto</th><th class="text-right">Pagado</th><th class="text-right">Saldo</th><th>Estado</th></tr></thead>
                <tbody>
                @forelse ($cuentas as $c)
                    <tr><td>{{ fecha($c->fecha) }}</td><td class="text-xs">{{ $c->observaciones }}</td><td class="text-right">{{ soles($c->monto) }}</td>
                        <td class="text-right text-emerald-600">{{ soles($c->cobranzas->sum('monto')) }}</td><td class="text-right font-semibold">{{ soles($c->saldo) }}</td><td><x-status :value="$c->estado"/></td></tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">Sin créditos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div class="mt-5" x-show="tab === 'cobranzas'" x-cloak>
            <table class="table table-compact">
                <thead><tr><th>Fecha</th><th>Método</th><th>Operación</th><th>Origen</th><th class="text-right">Monto</th><th></th></tr></thead>
                <tbody>
                @forelse ($cobranzas as $c)
                    <tr>
                        <td>{{ fecha($c->fecha) }}</td><td>{{ $c->metodo_pago->label() }}</td><td class="text-xs">{{ $c->numero_operacion }}</td>
                        <td class="text-xs">{{ $c->liquidacionCobranza?->liquidacion?->codigo ?? 'Oficina · '.($c->user?->name ?? '') }}</td>
                        <td class="text-right font-semibold">{{ soles($c->monto) }}</td>
                        <td>@if (! $c->liquidacion_cobranza_id)<x-row-actions :delete="route('creditos.cobranzas.destroy', $c)" delete-text="La deuda volverá a quedar pendiente."/>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">Sin cobranzas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
    </div>
</x-modal>
