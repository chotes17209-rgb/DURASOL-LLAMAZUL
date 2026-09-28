<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $r->titulo }}</title>
    <style>
        @page { margin: 28px 30px 40px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: {{ $r->horizontal ? '8.2px' : '8.8px' }}; color: #1e293b; }
        .cab { width: 100%; border-bottom: 2px solid #1a3a80; padding-bottom: 6px; margin-bottom: 10px; }
        .cab td { vertical-align: middle; }
        .logos img { height: 30px; margin-right: 10px; }
        .titulo { font-size: 14px; font-weight: bold; color: #1a3a80; text-transform: uppercase; }
        .sub { color: #475569; margin-top: 2px; }
        .datos { margin-bottom: 8px; }
        .datos td { padding: 1px 12px 1px 0; }
        .datos .k { color: #64748b; }
        h3 { font-size: 9.5px; color: #1a3a80; text-transform: uppercase; margin: 12px 0 4px; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t th { background: #1a3a80; color: #fff; font-weight: bold; padding: 3px 4px; border: 1px solid #1a3a80; text-align: center; }
        table.t td { padding: 2.5px 4px; border: 1px solid #cfd6df; }
        table.t tr:nth-child(even) td { background: #f6f8fb; }
        table.t tr.total td { background: #e8edf5; font-weight: bold; }
        .n { text-align: right; white-space: nowrap; }
        .nota { color: #64748b; font-style: italic; margin-top: 3px; }
        .pie { position: fixed; bottom: -26px; left: 0; right: 0; color: #94a3b8; font-size: 7.5px; }
    </style>
</head>
<body>
<table class="cab">
    <tr>
        <td class="logos" style="width: 45%">
            <img src="{{ $logos[0] }}" alt="Durasol"><img src="{{ $logos[1] }}" alt="Llamazul" style="height: 18px">
        </td>
        <td style="text-align: right">
            <div class="titulo">{{ $r->titulo }}</div>
            @if ($r->subtitulo)<div class="sub">{{ $r->subtitulo }}</div>@endif
        </td>
    </tr>
</table>

@if ($datos)
    <table class="datos">
        @foreach (array_chunk($datos, 3) as $grupo)
            <tr>@foreach ($grupo as [$k, $v])<td class="k">{{ $k }}:</td><td><b>{{ $v }}</b></td>@endforeach</tr>
        @endforeach
    </table>
@endif

@foreach ($tablas as $t)
    @if ($t['titulo'])<h3>{{ $t['titulo'] }}</h3>@endif
    @php($tipos = array_values($t['columnas']))
    <table class="t">
        <thead><tr>@foreach (array_keys($t['columnas']) as $c)<th>{{ $c }}</th>@endforeach</tr></thead>
        <tbody>
        @forelse ($t['filas'] as $fila)
            <tr>@foreach ($fila as $i => $v)<td class="{{ ($tipos[$i] ?? 'texto') !== 'texto' ? 'n' : '' }}">{{ \App\Support\Reporte::formatear($v, $tipos[$i] ?? 'texto') }}</td>@endforeach</tr>
        @empty
            <tr><td colspan="{{ count($t['columnas']) }}" style="text-align: center; color: #94a3b8">Sin registros</td></tr>
        @endforelse
        @if ($t['total'] !== null)
            <tr class="total">@foreach ($t['total'] as $i => $v)<td class="{{ ($tipos[$i] ?? 'texto') !== 'texto' ? 'n' : '' }}">{{ \App\Support\Reporte::formatear($v, $tipos[$i] ?? 'texto') }}</td>@endforeach</tr>
        @endif
        </tbody>
    </table>
    @if ($t['nota'])<div class="nota">{{ $t['nota'] }}</div>@endif
@endforeach

<div class="pie">Durasol · Llamazul — generado el {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()?->name ?? 'sistema' }}</div>
<script type="text/php">
    if (isset($pdf)) { $pdf->page_text($pdf->get_width() - 80, $pdf->get_height() - 28, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 7, [0.58, 0.64, 0.72]); }
</script>
</body>
</html>
