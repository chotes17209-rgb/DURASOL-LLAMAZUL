@php
    $filas = $guia->detalles->map(fn ($d) => [
        'id' => $d->id, 'codigo' => $d->producto->codigo, 'guia' => $d->cantidad_guia, 'enviado' => $d->totalEnviado(), 'cambios' => $d->cambios_enviados,
        'llenos_recibidos' => $d->llenos_recibidos ?: $d->cantidad_guia, 'cambios_repuestos' => $d->cambios_repuestos ?: $d->cambios_enviados,
        'vacios_rechazados' => $d->vacios_rechazados, 'colores_rechazados' => $d->colores_rechazados,
    ])->values();
@endphp
<form method="POST" action="{{ route('logistica.guias.recibir.store', $guia) }}" data-ajax autocomplete="off"
      x-data="{ filas: @js($filas), retorno(f) { return (+f.llenos_recibidos||0) + (+f.cambios_repuestos||0) + (+f.vacios_rechazados||0) + (+f.colores_rechazados||0) } }">
    @csrf
    <x-modal :title="'Recibir guía '.$guia->numero_guia" :subtitle="$guia->empresa->nombre.' · '.$guia->instalacion->nombreMostrar().' · salió el '.fecha($guia->fecha_salida)" icon="arrow-down-tray" color="green">
        <div class="grid gap-4 md:grid-cols-3">
            <x-field.input name="fecha_recepcion" type="date" label="Fecha de retorno" :value="today()->format('Y-m-d')" required/>
            <x-field.input name="observaciones" label="Observaciones" :value="$guia->observaciones" class="md:col-span-2"/>
        </div>
        <div class="mt-6 overflow-hidden rounded-2xl ring-1 ring-slate-200">
            <table class="table table-compact">
                <thead>
                <tr><th>Producto</th><th class="text-right">Guía</th><th class="text-right">Salieron</th>
                    <th class="bg-emerald-50 text-right text-emerald-700">Llenos comprados</th><th class="bg-emerald-50 text-right text-emerald-700">Llenos por cambios</th>
                    <th class="bg-rose-50 text-right text-rose-700">Vacíos rechazados</th><th class="bg-rose-50 text-right text-rose-700">Colores rechazados</th><th class="text-center">Masa</th></tr>
                </thead>
                <tbody>
                <template x-for="f in filas" :key="f.id">
                    <tr>
                        <td class="font-mono font-bold" x-text="f.codigo"></td>
                        <td class="text-right" x-text="f.guia"></td>
                        <td class="text-right font-semibold" x-text="f.enviado"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${f.id}][llenos_recibidos]`" x-model="f.llenos_recibidos"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${f.id}][cambios_repuestos]`" x-model="f.cambios_repuestos" :max="f.cambios"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${f.id}][vacios_rechazados]`" x-model="f.vacios_rechazados"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${f.id}][colores_rechazados]`" x-model="f.colores_rechazados"></td>
                        <td class="text-center">
                            <span class="badge" :class="retorno(f) === f.enviado ? 'badge-green' : 'badge-red'" x-text="retorno(f) === f.enviado ? 'Cuadra' : (retorno(f) - f.enviado > 0 ? '+' : '') + (retorno(f) - f.enviado)"></span>
                        </td>
                    </tr>
                </template>
                </tbody>
            </table>
        </div>
        <p class="form-error hidden" data-error-for="detalles"></p>
        <p class="mt-3 text-xs text-slate-500">Todo balón que salió debe volver: como lleno comprado, como lleno de reposición por un cambio o como vacío rechazado por la planta.</p>
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="btn btn-success"><x-heroicon-o-check class="h-4 w-4"/> Registrar ingreso</button>
        </x-slot:footer>
    </x-modal>
</form>
