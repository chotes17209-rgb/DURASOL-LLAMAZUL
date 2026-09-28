<x-form-modal :title="'Cuotas de compra · '.ucfirst($mes->translatedFormat('F Y'))" subtitle="Meta mensual de compra por empresa y presentación. Deje vacío si no aplica."
              :action="route('compras.cuotas.update')" method="PUT">
    <input type="hidden" name="mes" value="{{ $mes->format('Y-m') }}">
    <table class="table table-grid">
        <thead><tr><th>Empresa</th>@foreach ($productos as $p)<th class="w-32 text-right">{{ $p->codigo }}</th>@endforeach</tr></thead>
        <tbody>
        @foreach ($empresas as $e)
            <tr>
                <td class="font-semibold text-slate-900">{{ $e->nombre }}</td>
                @foreach ($productos as $p)
                    <td class="!p-0"><input type="number" min="0" name="cuotas[{{ $e->id }}][{{ $p->id }}]" value="{{ $valores[$e->id.'-'.$p->id] ?? '' }}" class="cell-input font-semibold"></td>
                @endforeach
            </tr>
        @endforeach
        </tbody>
    </table>
</x-form-modal>
