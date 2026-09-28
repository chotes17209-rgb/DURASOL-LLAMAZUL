{{-- Últimas filas del parte diario relacionadas a un chofer, vehículo o instalación. --}}
<table class="table table-compact">
    <thead><tr><th>Fecha</th><th>Movimiento</th><th>Placa</th><th>Responsable</th><th>Lugar</th><th>Guía</th><th class="text-right">S-10</th><th class="text-right">S-45</th><th class="text-right">M-10</th><th class="text-right">Color</th></tr></thead>
    <tbody>
    @forelse ($movimientos as $m)
        <tr>
            <td><a class="text-brand-700 hover:underline" href="{{ route('logistica.partes.show', $m->parte->fecha->toDateString()) }}">{{ fecha($m->parte->fecha) }}</a></td>
            <td>{{ \App\Models\ParteFila::NOMBRES_BLOQUE[$m->bloque] ?? $m->bloque }}</td>
            <td class="font-mono text-xs">{{ $m->placa }}</td><td>{{ $m->responsable }}</td><td>{{ $m->lugar }}</td><td class="text-xs">{{ $m->numero_guia }}</td>
            <td class="text-right">{{ $m->s10 ?: '' }}</td><td class="text-right">{{ $m->s45 ?: '' }}</td><td class="text-right">{{ $m->m10 ?: '' }}</td>
            <td class="text-right">{{ ($m->color_s10 + $m->color_s45) ?: '' }}</td>
        </tr>
    @empty
        <tr><td colspan="10" class="py-4 text-center text-slate-400">Sin movimientos en los partes diarios.</td></tr>
    @endforelse
    </tbody>
</table>
