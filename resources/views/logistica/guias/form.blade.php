@php
    $filas = $productos->map(function ($p) use ($guia) {
        $d = $guia->detalles->firstWhere('producto_id', $p->id);
        return [
            'producto_id' => $p->id, 'codigo' => $p->codigo, 'nombre' => $p->nombre,
            'cantidad_guia' => $d->cantidad_guia ?? '', 'precio_compra' => $d ? (float) $d->precio_compra : '',
            'vacios_enviados' => $d->vacios_enviados ?? '', 'colores_enviados' => $d->colores_enviados ?? '', 'cambios_enviados' => $d->cambios_enviados ?? '',
        ];
    })->values();
    $config = [
        'empresaId' => $guia->empresa_id, 'instalacionId' => $guia->instalacion_id,
        'instalaciones' => $instalaciones->map(fn ($i) => ['id' => $i->id, 'empresa_id' => $i->empresa_id, 'label' => $i->nombreMostrar(), 'chofer_id' => $i->chofer_id, 'vehiculo_id' => $i->vehiculo_id])->values(),
        'precios' => $precios, 'filas' => $filas,
    ];
@endphp
<form method="POST" action="{{ $guia->exists ? route('logistica.guias.update', $guia) : route('logistica.guias.store') }}" data-ajax autocomplete="off" x-data="guiaEditor(@js($config))">
    @csrf
    @if ($guia->exists) <input type="hidden" name="_method" value="PUT"> @endif
    <x-modal :title="$guia->exists ? 'Editar guía '.$guia->numero_guia : 'Nueva guía · salida a planta'" subtitle="Registra lo que se compra y los balones que salen hacia la planta de Solgas." icon="building-office-2">
        <div class="grid gap-4 md:grid-cols-4">
            <div>
                <label class="form-label">Empresa <span class="text-rose-500">*</span></label>
                <select name="empresa_id" class="form-input" x-model="empresaId" @change="cambiarEmpresa()" required>
                    <option value="">Seleccionar...</option>
                    @foreach ($empresas as $e)<option value="{{ $e->id }}">{{ $e->nombre }}</option>@endforeach
                </select>
                <p class="form-error hidden" data-error-for="empresa_id"></p>
            </div>
            <div class="md:col-span-2">
                <label class="form-label">Instalación <span class="text-rose-500">*</span></label>
                <select name="instalacion_id" class="form-input" x-model="instalacionId" @change="cambiarInstalacion()" required>
                    <option value="">Seleccionar...</option>
                    <template x-for="i in instalacionesEmpresa" :key="i.id">
                        <option :value="String(i.id)" x-text="i.label" :selected="String(i.id) === instalacionId"></option>
                    </template>
                </select>
                <p class="form-error hidden" data-error-for="instalacion_id"></p>
            </div>
            <x-field.input name="numero_guia" label="N° de guía de remisión" :value="$guia->numero_guia" required placeholder="EG07-00012345"/>
            <x-field.input name="fecha_salida" type="date" label="Fecha de salida" :value="$guia->fecha_salida?->format('Y-m-d')" required/>
            <x-field.select name="vehiculo_id" label="Camión" :options="$vehiculos" :selected="$guia->vehiculo_id" tom/>
            <x-field.select name="chofer_id" label="Chofer" :options="$choferes" :selected="$guia->chofer_id" tom/>
            <x-field.input name="observaciones" label="Observaciones" :value="$guia->observaciones"/>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl ring-1 ring-slate-200">
            <table class="table table-compact">
                <thead>
                <tr><th rowspan="2">Producto</th><th colspan="2" class="bg-brand-50 text-center text-brand-700">Según guía (compra)</th><th colspan="3" class="bg-amber-50 text-center text-amber-700">Salen a planta</th><th rowspan="2" class="text-center">Control</th></tr>
                <tr><th class="text-right">Cantidad</th><th class="text-right">P. compra</th><th class="text-right">Vacíos plomo</th><th class="text-right">Vacíos color</th><th class="text-right">Cambios</th></tr>
                </thead>
                <tbody>
                <template x-for="(f, i) in filas" :key="f.producto_id">
                    <tr>
                        <td>
                            <input type="hidden" :name="`detalles[${i}][producto_id]`" :value="f.producto_id">
                            <p class="font-mono font-bold" x-text="f.codigo"></p><p class="text-[11px] text-slate-400" x-text="f.nombre"></p>
                        </td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${i}][cantidad_guia]`" x-model="f.cantidad_guia" @input="igualarVacios(f)"></td>
                        <td><input type="number" min="0" step="0.01" class="form-input w-24 text-right" :name="`detalles[${i}][precio_compra]`" x-model="f.precio_compra"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${i}][vacios_enviados]`" x-model="f.vacios_enviados"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${i}][colores_enviados]`" x-model="f.colores_enviados" @input="igualarVacios(f)"></td>
                        <td><input type="number" min="0" class="form-input w-24 text-right" :name="`detalles[${i}][cambios_enviados]`" x-model="f.cambios_enviados" @input="igualarVacios(f)"></td>
                        <td class="text-center">
                            <template x-if="(+f.cantidad_guia || 0) + enviados(f) === 0"><span class="text-slate-300">—</span></template>
                            <template x-if="(+f.cantidad_guia || 0) + enviados(f) > 0 && diferencia(f) === 0"><span class="badge badge-green">Cuadra</span></template>
                            <template x-if="diferencia(f) !== 0"><span class="badge badge-red" x-text="(diferencia(f) > 0 ? '+' : '') + diferencia(f)"></span></template>
                        </td>
                    </tr>
                </template>
                </tbody>
                <tfoot>
                <tr><td>Totales</td><td class="text-right" x-text="totalGuia"></td><td class="text-right text-xs" x-text="money(importe)"></td><td colspan="3" class="text-right" x-text="totalEnviado + ' balones salen'"></td><td></td></tr>
                </tfoot>
            </table>
        </div>
        <p class="form-error hidden" data-error-for="detalles"></p>

        <div class="mt-4 flex items-start gap-3 rounded-2xl bg-amber-50 p-4 text-sm text-amber-800 ring-1 ring-amber-200" x-show="hayDiferencia" x-cloak>
            <x-heroicon-o-exclamation-triangle class="h-5 w-5 shrink-0"/>
            <div>
                <p class="font-semibold">Lo que sale no coincide con la guía.</p>
                <label class="mt-1 inline-flex items-center gap-2"><input type="checkbox" class="form-check" name="acepto_diferencia" value="1" x-model="aceptoDiferencia"> Aceptar diferencia (quedará registrado)</label>
            </div>
        </div>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="btn btn-primary"><x-heroicon-o-check class="h-4 w-4"/> {{ $guia->exists ? 'Guardar cambios' : 'Registrar salida' }}</button>
        </x-slot:footer>
    </x-modal>
</form>
