@php
    $filas = $despacho->detalles->map(fn ($d) => [
        'id' => $d->id, 'codigo' => $d->producto->codigo, 'empresa' => $d->empresa->nombre, 'llenos_salida' => $d->llenos_salida,
        'llenos_retorno' => $d->llenos_retorno, 'vacios_retorno' => $d->vacios_retorno ?: '', 'colores_retorno' => $d->colores_retorno ?: '', 'cambios_retorno' => $d->cambios_retorno ?: '',
    ])->values();
@endphp
<form method="POST" action="{{ route('logistica.despachos.retorno.store', $despacho) }}" data-ajax autocomplete="off" x-data="despachoEditor({ filas: @js($filas) })">
    @csrf
    <x-modal :title="'Retorno de '.$despacho->chofer->alias.' · vuelta '.$despacho->vuelta" :subtitle="'Salió el '.fecha($despacho->fecha).' con '.$despacho->totalSalida().' balones'" icon="arrow-uturn-left" color="green">
        <div class="overflow-hidden rounded-2xl ring-1 ring-slate-200">
            <table class="table table-compact">
                <thead><tr><th>Producto</th><th>Empresa</th><th class="text-right">Salieron</th><th class="bg-emerald-50 text-right text-emerald-700">Llenos que regresan</th>
                    <th class="text-right">Vacíos plomo</th><th class="text-right">Vacíos color</th><th class="text-right">Cambios (fallados)</th><th class="bg-brand-50 text-right text-brand-700">Vendidos</th></tr></thead>
                <tbody>
                <template x-for="f in filas" :key="f.id">
                    <tr>
                        <td class="font-mono font-bold" x-text="f.codigo"></td>
                        <td x-text="f.empresa"></td>
                        <td class="text-right font-semibold" x-text="f.llenos_salida"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${f.id}][llenos_retorno]`" x-model="f.llenos_retorno"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${f.id}][vacios_retorno]`" x-model="f.vacios_retorno"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${f.id}][colores_retorno]`" x-model="f.colores_retorno"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${f.id}][cambios_retorno]`" x-model="f.cambios_retorno"></td>
                        <td class="text-right text-lg font-bold text-brand-700" x-text="vendidos(f)"></td>
                    </tr>
                </template>
                </tbody>
                <tfoot><tr><td colspan="2">Totales</td><td class="text-right" x-text="total('llenos_salida')"></td><td class="text-right" x-text="total('llenos_retorno')"></td>
                    <td class="text-right" x-text="total('vacios_retorno')"></td><td class="text-right" x-text="total('colores_retorno')"></td><td class="text-right" x-text="total('cambios_retorno')"></td><td class="text-right text-brand-700" x-text="totalVendidos"></td></tr></tfoot>
            </table>
        </div>
        <p class="form-error hidden" data-error-for="detalles"></p>
        <div class="mt-4 grid gap-4 md:grid-cols-3">
            <x-field.input name="hora_retorno" type="time" label="Hora de retorno" :value="now()->format('H:i')"/>
            <x-field.input name="observaciones" label="Observaciones" :value="$despacho->observaciones" class="md:col-span-2"/>
        </div>
        <p class="mt-3 text-xs text-slate-500"><b>Vendidos</b> = salieron − llenos que regresan − cambios. Cada balón fallado que trae el chofer se reemplazó con un lleno, por eso no cuenta como venta.</p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="btn btn-success"><x-heroicon-o-check class="h-4 w-4"/> Registrar retorno</button>
        </x-slot:footer>
    </x-modal>
</form>
