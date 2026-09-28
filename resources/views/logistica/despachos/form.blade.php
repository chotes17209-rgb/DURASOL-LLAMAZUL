@php
    $valor = fn ($empresaId, $productoId) => optional($despacho->detalles->first(fn ($d) => $d->empresa_id == $empresaId && $d->producto_id == $productoId))->llenos_salida;
    $disponible = function ($empresa, $producto) use ($stock) {
        if ($producto->tipo === \App\Models\Producto::TIPO_ENVASE) {
            return $stock['vacio'][$producto->envase_id ?? $producto->id][0] ?? 0;
        }
        return $stock['lleno'][$producto->id][$empresa->id] ?? 0;
    };
@endphp
<form method="POST" action="{{ $despacho->exists ? route('logistica.despachos.update', $despacho) : route('logistica.despachos.store') }}" data-ajax autocomplete="off"
      x-data="despachoEditor({ vehiculos: @js($vehiculosPorChofer), tipos: @js($tiposPorChofer) })">
    @csrf
    @if ($despacho->exists) <input type="hidden" name="_method" value="PUT"> @endif
    <x-modal :title="$despacho->exists ? 'Editar despacho' : 'Salida de balones a chofer'" subtitle="Los choferes cuentan y registran lo que sacan; logística lo confirma aquí." icon="truck">
        <div class="grid gap-4 md:grid-cols-4">
            <x-field.select name="chofer_id" label="Chofer" :options="$choferes" :selected="$despacho->chofer_id" required tom x-on:change="cambiarChofer($event)" class="md:col-span-2"/>
            <x-field.input name="fecha" type="date" label="Fecha" :value="$despacho->fecha?->format('Y-m-d')" required/>
            <x-field.input name="vuelta" type="number" min="1" label="N° de vuelta" :value="$despacho->vuelta" required hint="1ra, 2da salida del día..."/>
            <x-field.select name="vehiculo_id" label="Vehículo" :options="$vehiculos" :selected="$despacho->vehiculo_id" tom/>
            <x-field.select name="tipo" label="Tipo de reparto" :options="\App\Enums\TipoChofer::options()" :selected="$despacho->tipo ?? 'local'" :empty="false"/>
            <x-field.input name="destino" label="Destino (si es ruta)" :value="$despacho->destino" placeholder="Huancavelica, Ayacucho..."/>
            <x-field.input name="hora_salida" type="time" label="Hora de salida" :value="$despacho->hora_salida ? substr($despacho->hora_salida, 0, 5) : null"/>
        </div>

        <div class="mt-6 overflow-hidden rounded-2xl ring-1 ring-slate-200">
            <table class="table table-compact">
                <thead><tr><th>Producto</th>@foreach ($empresas as $e)<th class="text-right">{{ $e->nombre }} <span class="font-normal normal-case text-slate-400">(disponible)</span></th>@endforeach</tr></thead>
                <tbody>
                @foreach ($productos as $p)
                    <tr>
                        <td><p class="font-mono font-bold">{{ $p->codigo }}</p><p class="text-[11px] text-slate-400">{{ $p->nombre }}</p></td>
                        @foreach ($empresas as $e)
                            <td class="text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <span class="text-xs text-slate-400">{{ num($disponible($e, $p)) }}</span>
                                    <input type="number" min="0" class="form-input w-24 text-right" name="detalles[{{ $e->id }}][{{ $p->id }}][llenos_salida]" value="{{ $valor($e->id, $p->id) }}" placeholder="0">
                                </div>
                            </td>
                        @endforeach
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
        <p class="form-error hidden" data-error-for="detalles"></p>
        <p class="form-error hidden" data-error-for="stock"></p>
        <x-field.textarea name="observaciones" label="Observaciones" :value="$despacho->observaciones" rows="2" class="mt-4"/>

        <x-slot:footer>
            <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="btn btn-primary"><x-heroicon-o-check class="h-4 w-4"/> {{ $despacho->exists ? 'Guardar cambios' : 'Registrar salida' }}</button>
        </x-slot:footer>
    </x-modal>
</form>
