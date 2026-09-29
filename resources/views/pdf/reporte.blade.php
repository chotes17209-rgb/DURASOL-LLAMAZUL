<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $r->titulo }}</title>
    <style>
        @page { margin: 96px 28px 46px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $r->horizontal ? '7.6px' : '8.2px' }}; color: #111; }
        .datos { border-collapse: collapse; margin-bottom: 8px; }
        .datos td { border: 1px solid #8a96a3; padding: 2.5px 7px; }
        .datos .k { background: #bdd7ee; font-weight: bold; white-space: nowrap; }
        .datos .v { font-weight: bold; }
        h3 { font-size: 8.4px; text-transform: uppercase; margin: 11px 0 3px; }
        table.t { width: 100%; border-collapse: collapse; page-break-inside: auto; }
        table.t thead { display: table-header-group; }
        table.t tr { page-break-inside: avoid; }
        table.t th { background: #bdd7ee; font-weight: bold; padding: 3px 3px; border: 1px solid #5f6b78; text-align: center; }
        table.t td { padding: 2.2px 3px; border: 1px solid #9aa4af; }
        table.t tr.total td { background: #bdd7ee; font-weight: bold; }
        .n { text-align: right; white-space: nowrap; }
        .vacio { text-align: center; color: #888; padding: 6px; }
        .nota { color: #555; font-style: italic; margin-top: 3px; }
    </style>
</head>
<body>
@include('pdf._cabecera', [
    'titulo' => $r->titulo,
    'etiquetaFecha' => $r->subtitulo ? 'PERIODO' : 'FECHA',
    'valorFecha' => $r->subtitulo ?? now()->format('d/m/Y'),
])

@if ($datos)
    <table class="datos">
        @foreach (array_chunk($datos, $r->horizontal ? 4 : 3) as $grupo)
            <tr>@foreach ($grupo as [$k, $v])<td class="k">{{ mb_strtoupper($k) }}</td><td class="v">{{ $v }}</td>@endforeach</tr>
        @endforeach
    </table>
@endif

@foreach ($tablas as $t)
    @if ($t['titulo'])<h3>{{ $t['titulo'] }}</h3>@endif
    @php($tipos = array_values($t['columnas']))
    <table class="t">
        <thead><tr>@foreach (array_keys($t['columnas']) as $c)<th>{{ mb_strtoupper($c) }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($t['filas'] as $fila)
            <tr>@foreach ($fila as $i => $v)<td class="{{ ($tipos[$i] ?? 'texto') !== 'texto' ? 'n' : '' }}">{{ \App\Support\Reporte::formatear($v, $tipos[$i] ?? 'texto') }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($t['columnas']) }}" class="vacio">Sin registros</td></tr>
        @endforelse
        @if ($t['total'] !== null)
            <tr class="total">@foreach ($t['total'] as $i => $v)<td class="{{ ($tipos[$i] ?? 'texto') !== 'texto' ? 'n' : '' }}">{{ \App\Support\Reporte::formatear($v, $tipos[$i] ?? 'texto') }}</td>@endforeach</tr>
        @endif
        </tbody>
    </table>
    @if ($t['nota'])<div class="nota">{{ $t['nota'] }}</div>@endif
@endforeach
@include('pdf._paginacion')
</body>
</html>
