<x-modal :title="$documento->tipo->label().' · '.$documento->vehiculo?->placa" icon="document-check">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'historial' => 'Historial']"/>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Número</dt><dd>{{ $documento->numero ?: '—' }}</dd></div>
                <div><dt>Entidad</dt><dd>{{ $documento->entidad ?: '—' }}</dd></div>
                <div><dt>Estado</dt><dd><x-status :value="$documento->estadoVencimiento()"/></dd></div>
                <div><dt>Emisión</dt><dd>{{ fecha($documento->fecha_emision) ?: '—' }}</dd></div>
                <div><dt>Vencimiento</dt><dd>{{ fecha($documento->fecha_vencimiento) ?: '—' }}</dd></div>
                <div><dt>Costo</dt><dd>{{ $documento->costo ? soles($documento->costo) : '—' }}</dd></div>
                <div class="col-span-full"><dt>Observaciones</dt><dd>{{ $documento->observaciones ?: '—' }}</dd></div>
                @if ($documento->archivo)
                    <div><dt>Archivo</dt><dd><a class="text-brand-600 hover:underline" target="_blank" href="{{ route('documentos.archivo', $documento) }}">Abrir archivo</a></dd></div>
                @endif
            </dl>
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$documento"/></div>
    </div>
</x-modal>
