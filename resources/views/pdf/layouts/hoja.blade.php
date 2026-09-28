{{-- Formato de hoja Excel de la empresa: título en recuadro, fecha en amarillo, cabeceras celestes. Una sola hoja. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $tituloHoja }} {{ $fecha->format('d/m/Y') }}</title>
    <style>
        @page { margin: 26px 26px 38px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 6.6px; color: #111; }
        .cab { width: 100%; margin-bottom: 6px; }
        .cab td { vertical-align: middle; }
        .logos img { height: 28px; margin-right: 8px; }
        .titulo { border: 2px solid #111; text-align: center; font-size: 13px; font-weight: bold; padding: 4px; letter-spacing: .5px; }
        .fecha { border-collapse: collapse; margin-left: auto; }
        .fecha td { border: 1px solid #555; padding: 3px 8px; font-weight: bold; }
        .fecha .k { background: #bdd7ee; }
        .fecha .v { background: #ffff00; font-size: 10px; }
        .datos { border-collapse: collapse; margin-bottom: 4px; }
        .datos td { border: 1px solid #888; padding: 2.5px 6px; font-size: 7.4px; }
        .datos .k { background: #bdd7ee; font-weight: bold; }
        .datos .v { font-weight: bold; }
        h3 { font-size: 8px; margin: 9px 0 3px; text-transform: uppercase; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t th { background: #bdd7ee; font-weight: bold; padding: 2px 2px; border: 1px solid #555; text-align: center; }
        table.t td { padding: 1.8px 2px; border: 1px solid #888; }
        table.t tr.total td { background: #bdd7ee; font-weight: bold; }
        table.t tr.sub td { background: #eef3fa; font-weight: bold; }
        table.t tr.grande td { font-size: 8.5px; }
        .n { text-align: right; white-space: nowrap; }
        .rojo { color: #c00000; font-weight: bold; }
        .vacio { text-align: center; color: #888; padding: 5px; }
        .pie { position: fixed; bottom: -24px; left: 0; right: 0; color: #888; font-size: 7px; }
    </style>
</head>
<body>
<table class="cab">
    <tr>
        <td class="logos" style="width: 25%"><img src="{{ $logos[0] }}" alt="Durasol"><img src="{{ $logos[1] }}" alt="Llamazul" style="height: 17px"></td>
        <td style="width: 50%"><div class="titulo">{{ mb_strtoupper($tituloHoja) }}</div></td>
        <td style="width: 25%"><table class="fecha"><tr><td class="k">FECHA</td><td class="v">{{ $fecha->format('d/m/Y') }}</td></tr></table></td>
    </tr>
</table>

@yield('contenido')

<div class="pie">Durasol · Llamazul — generado el {{ now()->format('d/m/Y H:i') }} por {{ auth()->user()?->name ?? 'sistema' }}</div>
<script type="text/php">
    if (isset($pdf)) { $pdf->page_text($pdf->get_width() - 80, $pdf->get_height() - 26, "Página {PAGE_NUM} de {PAGE_COUNT}", null, 7, [0.5, 0.5, 0.5]); }
</script>
</body>
</html>
