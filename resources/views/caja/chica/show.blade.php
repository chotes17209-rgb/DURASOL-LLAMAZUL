<x-modal :title="($m->esReposicion() ? 'Reposición' : 'Gasto').' de caja chica · '.fecha($m->fecha)" :subtitle="$m->descripcion">
    <dl class="dl-grid">
        <div><dt>Tipo</dt><dd>{{ $m->esReposicion() ? 'Reposición de fondo' : 'Gasto' }}</dd></div>
        <div><dt>Concepto</dt><dd>{{ $m->concepto }}</dd></div>
        <div><dt>Monto</dt><dd>{{ soles($m->monto) }}</dd></div>
        <div><dt>N° comprobante</dt><dd>{{ $m->comprobante ?? '—' }}</dd></div>
        <div><dt>Proveedor</dt><dd>{{ $m->proveedor ?? '—' }}</dd></div>
        <div><dt>RUC / DNI</dt><dd>{{ $m->ruc ?? '—' }}</dd></div>
        <div><dt>Vehículo</dt><dd>{{ $m->vehiculo?->placa ?? '—' }}</dd></div>
        <div><dt>Conductor</dt><dd>{{ $m->chofer?->nombre_completo ?: ($m->chofer?->alias ?? '—') }}</dd></div>
        <div><dt>Registró</dt><dd>{{ $m->user?->name ?? '—' }} · {{ $m->created_at?->format('d/m/Y H:i') }}</dd></div>
        <div class="col-span-2 sm:col-span-3"><dt>Observación</dt><dd>{{ $m->observacion ?? '—' }}</dd></div>
    </dl>
    <x-slot:footer>
        <button type="button" class="btn btn-secondary" data-modal-close>Cerrar</button>
        <button type="button" class="btn btn-primary" data-modal-url="{{ route('caja.chica.edit', $m) }}" data-modal-size="lg">Editar</button>
    </x-slot:footer>
</x-modal>
