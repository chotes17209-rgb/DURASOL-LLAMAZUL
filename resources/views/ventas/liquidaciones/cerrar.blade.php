<x-form-modal :title="'Cerrar liquidación '.$liquidacion->codigo" :subtitle="$liquidacion->chofer->alias.' · venta del '.fecha($liquidacion->fecha_venta)" icon="lock-closed"
              :action="route('liquidaciones.cerrar.store', $liquidacion)" submit="Cerrar y registrar en caja" confirm="¿Cerrar la liquidación? Se generarán los créditos y el ingreso a caja.">
    <div x-data="{ entregado: '{{ $liquidacion->efectivo_entregado ?? $liquidacion->efectivo_esperado }}', esperado: {{ (float) $liquidacion->efectivo_esperado }} }">
        <dl class="space-y-2 rounded bg-slate-50 p-5 text-sm border border-line">
            <div class="flex justify-between"><dt>Venta total ({{ $liquidacion->totalBalones() }} balones)</dt><dd class="font-semibold">{{ soles($liquidacion->total_venta) }}</dd></div>
            <div class="flex justify-between"><dt>+ Cobranzas</dt><dd class="text-emerald-600">{{ soles($liquidacion->total_cobranzas) }}</dd></div>
            <div class="flex justify-between"><dt>− Créditos</dt><dd class="text-rose-600">{{ soles($liquidacion->total_credito) }}</dd></div>
            <div class="flex justify-between"><dt>− Vouchers</dt><dd class="text-rose-600">{{ soles($liquidacion->total_vouchers) }}</dd></div>
            <div class="flex justify-between"><dt>− FISE</dt><dd class="text-rose-600">{{ soles($liquidacion->total_fises) }}</dd></div>
            <div class="flex justify-between"><dt>− Gastos</dt><dd class="text-rose-600">{{ soles($liquidacion->total_gastos) }}</dd></div>
            <div class="flex justify-between border-t border-slate-200 pt-2 text-base"><dt class="font-bold">Efectivo esperado</dt><dd class="font-extrabold">{{ soles($liquidacion->efectivo_esperado) }}</dd></div>
        </dl>
        <div class="mt-5">
            <x-field.input name="efectivo_entregado" type="number" step="0.01" min="0" label="Efectivo contado y recibido en caja" prefix="S/" required x-model="entregado"/>
            <p class="mt-2 text-sm font-semibold" :class="Math.abs(entregado - esperado) < 0.005 ? 'text-emerald-600' : (entregado > esperado ? 'text-sky-600' : 'text-rose-600')"
               x-text="Math.abs(entregado - esperado) < 0.005 ? '✔ Cuadra exacto' : ((entregado > esperado ? 'Sobran S/ ' : 'Faltan S/ ') + Math.abs(entregado - esperado).toFixed(2))"></p>
        </div>
        @if ($liquidacion->cobranzas->isNotEmpty())
            <p class="mt-4 text-xs text-slate-500">Las cobranzas se aplicarán a las deudas más antiguas de cada cliente.</p>
        @endif
    </div>
</x-form-modal>
