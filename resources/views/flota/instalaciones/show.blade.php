<x-modal :title="$instalacion->codigo.' · '.$instalacion->nombre" :subtitle="$instalacion->empresa?->nombre" icon="map-pin">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'precios' => 'Historial de precios', 'guias' => 'Cargas en planta', 'historial' => 'Historial']"/>
        <div x-show="tab === 'detalle'" class="space-y-5">
            <dl class="dl-grid">
                <div><dt>Código</dt><dd class="font-mono">{{ $instalacion->codigo }}</dd></div>
                <div><dt>Empresa</dt><dd>{{ $instalacion->empresa?->nombre }}</dd></div>
                <div><dt>Estado</dt><dd>{{ $instalacion->activo ? 'Activa' : 'Inactiva' }}</dd></div>
                <div><dt>Chofer designado</dt><dd>{{ $instalacion->chofer?->alias ?? '—' }}</dd></div>
                <div><dt>Camión designado</dt><dd>{{ $instalacion->vehiculo?->placa ?? '—' }}</dd></div>
                <div><dt>Dirección</dt><dd>{{ $instalacion->direccion ?: '—' }}</dd></div>
            </dl>
            <div>
                <p class="mb-2 text-sm font-semibold">Precios de compra vigentes</p>
                <div class="grid gap-3 sm:grid-cols-3">
                    @forelse ($vigentes as $precio)
                        <div class="rounded bg-slate-50 p-4 border border-line">
                            <p class="text-xs font-bold text-slate-500">{{ $precio->producto?->codigo }} · {{ $precio->producto?->nombre }}</p>
                            <p class="mt-1 text-xl font-bold text-slate-900">{{ soles($precio->precio) }}</p>
                            <p class="text-[11px] text-slate-400">desde {{ fecha($precio->vigente_desde) }}</p>
                        </div>
                    @empty
                        <p class="text-sm text-slate-400">Sin precios registrados.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div x-show="tab === 'precios'" x-cloak>
            <table class="table table-compact">
                <thead><tr><th>Vigente desde</th><th>Producto</th><th class="text-right">Precio</th><th>Motivo</th><th>Registró</th></tr></thead>
                <tbody>
                @forelse ($historialPrecios as $p)
                    <tr><td>{{ fecha($p->vigente_desde) }}</td><td class="font-mono">{{ $p->producto?->codigo }}</td><td class="text-right font-semibold">{{ soles($p->precio) }}</td><td class="text-xs">{{ $p->motivo }}</td><td class="text-xs">{{ $p->user?->name ?? 'Importado' }}</td></tr>
                @empty
                    <tr><td colspan="5" class="text-center text-slate-400">Sin historial.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div x-show="tab === 'guias'" x-cloak>
            @include('logistica.partes._movimientos')
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$instalacion"/></div>
    </div>
</x-modal>
