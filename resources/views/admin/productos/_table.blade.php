@php($tipos = ['gas' => ['Gas', 'orange'], 'envase' => ['Envase vacío', 'slate'], 'accesorio' => ['Accesorio', 'violet']])
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Código</th><th>Producto</th><th>Marca</th><th>Tipo</th><th>Capacidad</th><th>Envase</th><th>Planta</th><th>Stock</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($productos as $p)
            <tr>
                <td class="font-mono font-bold text-slate-900">{{ $p->codigo }}</td>
                <td>{{ $p->nombre }}</td>
                <td>{{ $p->marca ?: '—' }}</td>
                <td><x-badge :color="$tipos[$p->tipo][1] ?? 'slate'">{{ $tipos[$p->tipo][0] ?? $p->tipo }}</x-badge></td>
                <td>{{ $p->capacidad_kg ? $p->capacidad_kg.' kg' : '—' }}</td>
                <td class="font-mono text-xs">{{ $p->envase?->codigo ?? '—' }}</td>
                <td>@if($p->se_compra_en_planta)<x-heroicon-s-check-circle class="h-5 w-5 text-emerald-700"/>@endif</td>
                <td>@if($p->controla_stock)<x-heroicon-s-check-circle class="h-5 w-5 text-emerald-700"/>@endif</td>
                <td><x-badge :color="$p->activo ? 'green' : 'red'">{{ $p->activo ? 'Activo' : 'Inactivo' }}</x-badge></td>
                <td><x-row-actions size="md" :show="route('productos.show', $p)" :edit="route('productos.edit', $p)" :delete="route('productos.destroy', $p)"/></td>
            </tr>
        @empty
            <tr><td colspan="10"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $productos->links() }}
