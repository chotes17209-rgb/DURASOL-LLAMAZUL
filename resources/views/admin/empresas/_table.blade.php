@if ($empresas->isEmpty())
    <x-empty/>
@else
<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Empresa</th><th>RUC</th><th>Teléfono</th><th class="text-center">Instalaciones</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @foreach ($empresas as $empresa)
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <span class="h-8 w-8 rounded" style="background: {{ $empresa->color }}"></span>
                        <div><p class="font-semibold text-slate-900">{{ $empresa->nombre }}</p><p class="text-xs text-slate-500">{{ $empresa->razon_social }}</p></div>
                    </div>
                </td>
                <td class="font-mono text-xs">{{ $empresa->ruc ?: '—' }}</td>
                <td>{{ $empresa->telefono ?: '—' }}</td>
                <td class="text-center">{{ $empresa->instalaciones_count }}</td>
                <td><x-badge :color="$empresa->activo ? 'green' : 'red'">{{ $empresa->activo ? 'Activa' : 'Inactiva' }}</x-badge></td>
                <td><x-row-actions size="md" :show="route('empresas.show', $empresa)" :edit="route('empresas.edit', $empresa)" :delete="route('empresas.destroy', $empresa)"/></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</div>
{{ $empresas->links() }}
@endif
