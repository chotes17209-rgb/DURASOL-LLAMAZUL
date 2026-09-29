{{-- Formato de hoja Excel de la empresa: cabecera común, cabeceras celestes y totales resaltados. Una sola hoja. --}}
<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <title>{{ $tituloHoja }} {{ $fecha->format('d/m/Y') }}</title>
    <style>
        @page { margin: 94px 26px 44px; }
        body { font-family: DejaVu Sans, sans-serif; font-size: 6.6px; color: #111; }
        .datos { border-collapse: collapse; margin-bottom: 4px; }
        .datos td { border: 1px solid #888; padding: 2.5px 6px; font-size: 7.4px; }
        .datos .k { background: #bdd7ee; font-weight: bold; }
        .datos .v { font-weight: bold; }
        h3 { font-size: 8px; margin: 8px 0 3px; text-transform: uppercase; }
        table.t { width: 100%; border-collapse: collapse; }
        table.t th { background: #bdd7ee; font-weight: bold; padding: 2px 2px; border: 1px solid #555; text-align: center; }
        table.t td { padding: 1.8px 2px; border: 1px solid #888; }
        table.t tr.total td { background: #bdd7ee; font-weight: bold; }
        table.t tr.sub td { background: #eef3fa; font-weight: bold; }
        table.t tr.grande td { font-size: 8.5px; }
        .n { text-align: right; white-space: nowrap; }
        .rojo { color: #c00000; font-weight: bold; }
        .vacio { text-align: center; color: #888; padding: 5px; }
    </style>
</head>
<body>
@include('pdf._cabecera', ['titulo' => $tituloHoja, 'etiquetaFecha' => 'FECHA', 'valorFecha' => $fecha->format('d/m/Y')])

@yield('contenido')
@include('pdf._paginacion')
</body>
</html>
