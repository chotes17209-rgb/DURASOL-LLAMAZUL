<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Código</th><th>Instalación</th><th>Empresa</th><th>Chofer</th><th>Camión</th>
            @foreach ($productos as $p)<th class="text-right">P. compra {{ $p->codigo }}</th>@endforeach
            <th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($instalaciones as $i)
            <tr>
                <td class="font-mono font-bold text-slate-900">{{ $i->codigo }}</td>
                <td>{{ $i->nombre }}<p class="text-xs text-slate-400">{{ $i->direccion }}</p></td>
                <td><x-badge color="blue">{{ $i->empresa?->nombre }}</x-badge></td>
                <td>{{ $i->chofer?->alias ?? '—' }}</td>
                <td>{{ $i->vehiculo?->placa ?? '—' }}</td>
                @foreach ($productos as $p)
                    <td class="text-right tabular-nums">{{ isset($vigentes[$i->id][$p->id]) ? soles($vigentes[$i->id][$p->id]->precio) : '—' }}</td>
                @endforeach
                <td><x-badge :color="$i->activo ? 'green' : 'red'">{{ $i->activo ? 'Activa' : 'Inactiva' }}</x-badge></td>
                <td><x-row-actions :show="route('instalaciones.show', $i)" :edit="route('instalaciones.edit', $i)" :delete="route('instalaciones.destroy', $i)"/></td>
            </tr>
        @empty
            <tr><td colspan="{{ 7 + $productos->count() }}"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $instalaciones->links() }}
