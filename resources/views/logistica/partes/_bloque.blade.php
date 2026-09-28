{{--
    Tabla editable de un bloque del parte.
    $bloque: clave del bloque · $titulo · $columnas: [campo => título] · $conPlanta: muestra empresa / instalación / guía
--}}
@php($campos = array_keys($columnas))
<div class="card">
    <div class="card-header">
        <p class="card-title">{{ $titulo }}</p>
        <div class="flex items-center gap-3 text-xs text-slate-500">
            <span>Total: <b class="text-slate-800" x-text="n(totalBloque('{{ $bloque }}', @js($campos)))"></b></span>
            <button type="button" class="btn btn-secondary btn-sm no-print" x-show="editable" @click="agregar('{{ $bloque }}')"><x-heroicon-o-plus/> Filas</button>
        </div>
    </div>
    <div class="table-wrap">
        <table class="table table-compact table-grid">
            <thead>
            <tr class="th-group">
                <th colspan="{{ $conPlanta ? 6 : 4 }}">{{ $conPlanta ? 'Vehículo, responsable y carga en planta' : 'Vehículo y responsable' }}</th>
                <th colspan="{{ count($columnas) + 1 }}">Cantidades (balones)</th>
                <th colspan="2"></th>
            </tr>
            <tr>
                <th class="w-8 text-center">#</th>
                <th class="w-28">Placa</th>
                <th class="w-36">Responsable</th>
                <th class="w-28">{{ $bloque === 'vacio_salida' ? 'Destino' : 'Lugar' }}</th>
                @if ($conPlanta)
                    <th class="w-40">Instalación (planta)</th>
                    <th class="w-28">N° guía</th>
                @endif
                @foreach ($columnas as $titulo)<th class="w-20 text-right">{{ $titulo }}</th>@endforeach
                <th class="w-16 text-right">Total</th>
                <th>Observación</th>
                <th class="w-8"></th>
            </tr>
            </thead>
            <tbody>
            <template x-for="(fila, i) in bloques['{{ $bloque }}']" :key="fila.uid">
                <tr>
                    <td class="bg-panel text-center text-[12px] text-slate-400" x-text="i + 1"></td>
                    <td class="!p-0"><input class="cell-input text-left uppercase" list="lista-placas" x-model="fila.placa" @change="completarPorPlaca(fila)" :disabled="!editable"></td>
                    <td class="!p-0"><input class="cell-input text-left uppercase" list="lista-choferes" x-model="fila.responsable" :disabled="!editable"></td>
                    <td class="!p-0"><input class="cell-input text-left uppercase" list="lista-lugares" x-model="fila.lugar" :disabled="!editable"></td>
                    @if ($conPlanta)
                        <td class="!p-0">
                            <select class="cell-input text-left" x-model="fila.instalacion_id" @change="elegirInstalacion(fila)" :disabled="!editable">
                                <option value="">—</option>
                                @foreach ($instalaciones as $inst)
                                    <option value="{{ $inst->id }}">{{ $inst->codigo }} · {{ $inst->empresa?->nombre }} · {{ $inst->responsable ?? $inst->nombre }}</option>
                                @endforeach
                            </select>
                        </td>
                        <td class="!p-0"><input class="cell-input text-left uppercase" x-model="fila.numero_guia" :disabled="!editable"></td>
                    @endif
                    @foreach ($campos as $campo)
                        <td class="!p-0"><input type="number" min="0" inputmode="numeric" class="cell-input" x-model="fila.{{ $campo }}" :disabled="!editable" @keydown.enter.prevent="siguienteFila('{{ $bloque }}', fila, $event)"></td>
                    @endforeach
                    <td class="text-right font-semibold" x-text="totalFila(fila, @js($campos)) || ''"></td>
                    <td class="!p-0"><input class="cell-input text-left" x-model="fila.observacion" :disabled="!editable"></td>
                    <td class="!p-0 text-center"><button type="button" class="btn-icon danger" x-show="editable" tabindex="-1" @click="quitar('{{ $bloque }}', fila)" title="Quitar fila"><x-heroicon-o-x-mark/></button></td>
                </tr>
            </template>
            </tbody>
            <tfoot>
            <tr>
                <td colspan="{{ $conPlanta ? 6 : 4 }}">TOTAL</td>
                @foreach ($campos as $campo)<td class="text-right" x-text="n(totalColumna('{{ $bloque }}', '{{ $campo }}'))"></td>@endforeach
                <td class="text-right" x-text="n(totalBloque('{{ $bloque }}', @js($campos)))"></td>
                <td colspan="2"></td>
            </tr>
            </tfoot>
        </table>
    </div>
</div>
