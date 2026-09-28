<x-form-modal :title="'Ajustar stock a conteo físico · '.$dia->format('d/m/Y')" subtitle="Ingrese las cantidades contadas; la diferencia se registra como fila de AJUSTE." :action="route('logistica.partes.ajuste.store', $dia->toDateString())" submit="Registrar ajuste">
    <table class="table table-compact table-grid">
        <thead><tr><th>Concepto</th><th class="text-right">Stock según sistema</th><th class="w-32 text-right">Conteo físico</th></tr></thead>
        <tbody>
        @foreach ($control as $llave => $fila)
            <tr>
                <td>{{ $fila['tipo'] === 'lleno' ? 'Llenos' : 'Vacíos' }} · {{ $fila['titulo'] }}</td>
                <td class="text-right">{{ num($fila['final']) }}</td>
                <td class="!p-0"><input type="number" min="0" name="conteo[{{ $llave }}]" class="cell-input" placeholder="—"></td>
            </tr>
        @endforeach
        </tbody>
    </table>
</x-form-modal>
