<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Fecha</th><th>Estado</th><th class="text-right">Filas</th><th class="text-right">Ingreso llenos</th><th class="text-right">Salida llenos</th>
            <th class="text-right">Stock S-10</th><th class="text-right">Stock S-45</th><th class="text-right">Stock M-10</th><th class="text-right">Vacíos S-10</th><th></th></tr></thead>
        <tbody>
        @forelse ($partes as $p)
            @php($r = $resumen[$p->id])
            <tr>
                <td><a href="{{ route('logistica.partes.show', $p->fecha->toDateString()) }}" class="font-medium text-brand-700 hover:underline">{{ ucfirst($p->fecha->translatedFormat('D d/m/Y')) }}</a></td>
                <td>@if($p->esEditable())<span class="badge badge-green">Abierto</span>@else<span class="badge badge-slate">Cerrado</span>@endif</td>
                <td class="text-right">{{ $p->filas_count }}</td>
                <td class="text-right">{{ num($r['ingreso_llenos']) }}</td>
                <td class="text-right">{{ num($r['salida_llenos']) }}</td>
                <td class="text-right font-semibold">{{ num($r['final_s10']) }}</td>
                <td class="text-right">{{ num($r['final_s45']) }}</td>
                <td class="text-right">{{ num($r['final_m10']) }}</td>
                <td class="text-right">{{ num($r['vacios_s10']) }}</td>
                <td class="text-right"><a href="{{ route('logistica.partes.show', $p->fecha->toDateString()) }}" class="btn btn-secondary btn-sm">Abrir</a></td>
            </tr>
        @empty
            <tr><td colspan="10"><x-empty title="Aún no hay partes" text="Abre el parte del día para empezar a registrar."/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $partes->links() }}
