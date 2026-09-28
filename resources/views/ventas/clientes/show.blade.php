<x-modal :title="$cliente->nombre" :subtitle="'Código '.$cliente->codigo.($cliente->conocido_como ? ' · '.$cliente->conocido_como : '').($cliente->chofer ? ' · Chofer '.$cliente->chofer->alias : '')" icon="user">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'precios' => 'Precios', 'compras' => 'Compras', 'creditos' => 'Créditos', 'historial' => 'Historial']"/>

        <div x-show="tab === 'detalle'" class="space-y-6">
            <div class="grid gap-3 sm:grid-cols-4">
                @foreach ($vigentes as $productoId => $precio)
                    <div class="kpi">
                        <p class="kpi-label">Precio {{ $productos[$productoId]->codigo ?? '' }}</p>
                        <p class="kpi-value">{{ soles($precio) }}</p>
                    </div>
                @endforeach
                <div class="kpi border-l-[3px] {{ $cliente->deudaPendiente() > 0 ? 'border-l-red-600' : 'border-l-emerald-600' }}">
                    <p class="kpi-label">Deuda pendiente</p>
                    <p class="kpi-value">{{ soles($cliente->deudaPendiente()) }}</p>
                </div>
            </div>
            <dl class="dl-grid">
                <div><dt>Documento</dt><dd>{{ $cliente->documento ?: '—' }}</dd></div>
                <div><dt>Teléfono</dt><dd>{{ $cliente->telefono ?: '—' }}</dd></div>
                <div><dt>Correo</dt><dd>{{ $cliente->correo ?: '—' }}</dd></div>
                <div class="col-span-2"><dt>Dirección</dt><dd>{{ $cliente->direccion ?: '—' }}</dd></div>
                <div><dt>Zona</dt><dd>{{ $cliente->zona ?: '—' }}</dd></div>
                <div><dt>Tipo</dt><dd>{{ \App\Models\Cliente::TIPOS[$cliente->tipo] ?? $cliente->tipo }}</dd></div>
                <div><dt>Límite de crédito</dt><dd>{{ soles($cliente->limite_credito) }}</dd></div>
                <div><dt>Estado</dt><dd>{{ $cliente->activo ? 'Activo' : 'Inactivo' }}</dd></div>
            </dl>
            @if ($resumen->isNotEmpty())
                <div>
                    <p class="mb-2 text-sm font-semibold">Total comprado (histórico)</p>
                    <div class="flex flex-wrap gap-2">
                        @foreach ($resumen as $r)
                            <x-badge color="blue">{{ $productos[$r->producto_id]->codigo ?? '?' }}: {{ num($r->cantidad) }} und · {{ soles($r->total) }}</x-badge>
                        @endforeach
                    </div>
                </div>
            @endif
        </div>

        <div x-show="tab === 'precios'" x-cloak>
            @include('precios.venta._timeline', ['historial' => $historialPrecios, 'productos' => $productos])
        </div>

        <div x-show="tab === 'compras'" x-cloak>
            <table class="table table-compact">
                <thead><tr><th>Fecha</th><th>Liquidación</th><th>Chofer</th><th>Empresa</th><th>Prod.</th><th class="text-right">Cant.</th><th class="text-right">Precio</th><th class="text-right">Total</th><th>Pago</th></tr></thead>
                <tbody>
                @forelse ($compras as $i)
                    <tr>
                        <td>{{ fecha($i->liquidacion?->fecha_venta) }}</td>
                        <td class="font-mono text-xs">{{ $i->liquidacion?->codigo }}</td>
                        <td>{{ $i->liquidacion?->chofer?->alias }}</td>
                        <td>{{ $i->empresa?->nombre }}</td>
                        <td class="font-mono">{{ $i->producto?->codigo }}</td>
                        <td class="text-right">{{ $i->cantidad }}</td>
                        <td class="text-right">{{ num($i->precio, 2) }}</td>
                        <td class="text-right font-semibold">{{ soles($i->total) }}</td>
                        <td>@if($i->monto_credito > 0)<x-badge color="red">Crédito</x-badge>@else<x-badge color="green">{{ $i->metodo_pago->label() }}</x-badge>@endif</td>
                    </tr>
                @empty
                    <tr><td colspan="9" class="py-6 text-center text-slate-400">Sin compras registradas.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'creditos'" x-cloak>
            <table class="table table-compact">
                <thead><tr><th>Fecha</th><th>Detalle</th><th class="text-right">Monto</th><th class="text-right">Saldo</th><th>Estado</th><th></th></tr></thead>
                <tbody>
                @forelse ($cuentas as $c)
                    <tr>
                        <td>{{ fecha($c->fecha) }}</td><td class="text-xs">{{ $c->observaciones }}</td>
                        <td class="text-right">{{ soles($c->monto) }}</td><td class="text-right font-semibold">{{ soles($c->saldo) }}</td>
                        <td><x-status :value="$c->estado"/></td>
                        <td><x-row-actions size="md" :show="route('creditos.show', $c)"/></td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="py-6 text-center text-slate-400">Sin créditos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>

        <div x-show="tab === 'historial'" x-cloak><x-history :model="$cliente"/></div>
    </div>
</x-modal>
