{{-- Control de stock del tipo indicado ($llaves = claves de AlmacenService::STOCK). --}}
<div class="card">
    <div class="card-header"><p class="card-title">{{ $titulo }}</p></div>
    <table class="table table-compact table-grid">
        <thead>
        <tr><th class="w-32">Stock</th>@foreach ($llaves as $llave => $label)<th class="text-right">{{ $label }}</th>@endforeach</tr>
        </thead>
        <tbody>
        @foreach (['inicial' => 'Stock inicial', 'ingreso' => '(+) Ingreso', 'salida' => '(−) Salida'] as $clave => $label)
            <tr>
                <td class="font-medium">{{ $label }}</td>
                @foreach ($llaves as $llave => $_)<td class="text-right" x-text="n(control('{{ $llave }}').{{ $clave }})"></td>@endforeach
            </tr>
        @endforeach
        </tbody>
        <tfoot>
        <tr>
            <td>STOCK FINAL</td>
            @foreach ($llaves as $llave => $_)<td class="text-right" :class="control('{{ $llave }}').final < 0 && 'text-red-700'" x-text="n(control('{{ $llave }}').final)"></td>@endforeach
        </tr>
        </tfoot>
    </table>
</div>
