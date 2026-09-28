<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Vigente desde</th><th>Empresa</th><th>Instalación</th><th>Producto</th><th class="text-right">Precio</th><th>Motivo</th><th>Factura</th><th>Registrado por</th><th></th></tr></thead>
        <tbody>
        @forelse ($registros as $r)
            <tr>
                <td>{{ fecha($r->vigente_desde) }}</td>
                <td>{{ $r->empresa?->nombre }}</td>
                <td class="font-mono text-xs">{{ $r->instalacion?->codigo }} <span class="font-sans text-slate-500">{{ $r->instalacion?->nombre }}</span></td>
                <td class="font-mono">{{ $r->producto?->codigo }}</td>
                <td class="text-right font-semibold tabular-nums">{{ soles($r->precio) }}</td>
                <td class="text-xs">{{ $r->motivo }}</td>
                <td>@if ($r->validado)<span class="badge badge-green" title="{{ $r->validadoPor?->name }} {{ $r->validado_at?->format('d/m/Y') }}">Validado</span>@else<span class="badge badge-amber">No validado</span>@endif</td>
                <td>{{ $r->user?->name ?? 'Importado' }}</td>
                <td><x-row-actions size="md" :edit="route('precios.compra.edit', $r)" :delete="route('precios.compra.destroy', $r)" delete-text="Se elimina este registro del historial de precios."/></td>
            </tr>
        @empty
            <tr><td colspan="9"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $registros->links() }}
