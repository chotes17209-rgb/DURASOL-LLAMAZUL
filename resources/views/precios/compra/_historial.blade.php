<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Vigente desde</th><th>Empresa</th><th>Instalación</th><th>Producto</th><th class="text-right">Precio</th><th>Motivo</th><th>Registrado por</th><th></th></tr></thead>
        <tbody>
        @forelse ($registros as $r)
            <tr>
                <td>{{ fecha($r->vigente_desde) }}</td>
                <td>{{ $r->empresa?->nombre }}</td>
                <td class="font-mono text-xs">{{ $r->instalacion?->codigo }} <span class="font-sans text-slate-500">{{ $r->instalacion?->nombre }}</span></td>
                <td class="font-mono">{{ $r->producto?->codigo }}</td>
                <td class="text-right font-semibold tabular-nums">{{ soles($r->precio) }}</td>
                <td class="text-xs">{{ $r->motivo }}</td>
                <td>{{ $r->user?->name ?? 'Importado' }}</td>
                <td>@if (auth()->user()->isAdmin())<x-row-actions :delete="route('precios.compra.destroy', $r)"/>@endif</td>
            </tr>
        @empty
            <tr><td colspan="8"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $registros->links() }}
