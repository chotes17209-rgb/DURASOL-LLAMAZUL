@php($colores = ['local' => 'blue', 'ruta' => 'violet', 'planta' => 'orange', 'almacen' => 'sky', 'otro' => 'slate'])
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Chofer</th><th>Tipo</th><th>DNI</th><th>Teléfono</th><th>Vehículo</th><th class="text-center">Clientes</th><th>Licencia</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($choferes as $c)
            <tr>
                <td><p class="font-bold text-slate-900">{{ $c->alias }}</p><p class="text-xs text-slate-500">{{ $c->nombre_completo }}</p></td>
                <td><x-badge :color="$colores[$c->tipo->value] ?? 'slate'">{{ $c->tipo->label() }}</x-badge></td>
                <td class="font-mono text-xs">{{ $c->dni ?: '—' }}</td>
                <td>{{ $c->telefono ?: '—' }}</td>
                <td>@if($c->vehiculo)<span class="font-mono font-semibold text-slate-800">{{ $c->vehiculo->placa }}</span>@else — @endif</td>
                <td class="text-center">{{ $c->clientes_count }}</td>
                <td class="text-xs">
                    {{ $c->licencia_categoria }} {{ $c->licencia }}
                    @if ($c->licencia_vence)
                        <x-badge :color="$c->licencia_vence->isPast() ? 'red' : ($c->licencia_vence->diffInDays(today()) <= 30 ? 'amber' : 'green')">{{ $c->licencia_vence->format('d/m/Y') }}</x-badge>
                    @endif
                </td>
                <td><x-badge :color="$c->activo ? 'green' : 'red'">{{ $c->activo ? 'Activo' : 'Inactivo' }}</x-badge></td>
                <td><x-row-actions size="xl" :show="route('choferes.show', $c)" :edit="route('choferes.edit', $c)" :delete="route('choferes.destroy', $c)"/></td>
            </tr>
        @empty
            <tr><td colspan="9"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $choferes->links() }}
