{{--
    Cabecera y pie comunes a todos los reportes PDF. Se repiten en cada página.
    Parámetros: $titulo, $etiquetaFecha (FECHA / PERIODO), $valorFecha, $logos.
    La numeración de páginas va en pdf._paginacion, al final del documento.
--}}
<style>
    .doc-cab { position: fixed; top: -84px; left: 0; right: 0; height: 76px; }
    .doc-cab table { width: 100%; border-collapse: collapse; }
    .doc-cab td { vertical-align: middle; }
    .doc-logos { white-space: nowrap; }
    .doc-logos img { height: 30px; margin-right: 8px; }
    .doc-empresa { font-size: 7.4px; letter-spacing: .6px; color: #3a4a5c; text-align: center; margin-bottom: 3px; }
    .doc-titulo { border: 1.6px solid #111; text-align: center; font-size: 12.5px; font-weight: bold; padding: 4px 6px; letter-spacing: .4px; color: #111; }
    .doc-meta { border-collapse: collapse; margin-left: auto; }
    .doc-meta td { border: 1px solid #6b7785; padding: 2.5px 7px; font-size: 7.4px; font-weight: bold; white-space: nowrap; }
    .doc-meta .k { background: #bdd7ee; }
    .doc-meta .v-fecha { background: #ffff00; font-size: 9.5px; text-align: center; }
    .doc-meta .v { background: #fff; font-weight: normal; text-align: center; }
    .doc-linea { margin-top: 6px; border-top: 2px solid #173566; }
    .doc-linea2 { border-top: 1px solid #d9661a; margin-top: 1px; }
    .doc-pie { position: fixed; bottom: -30px; left: 0; right: 0; border-top: 1px solid #b8c1cc; padding-top: 3px; color: #6b7785; font-size: 6.8px; }
</style>
<div class="doc-cab">
    <table>
        <tr>
            <td class="doc-logos" style="width: 27%">
                <img src="{{ $logos[0] }}" alt="Mr. Durasol Perú S.A.C."><img src="{{ $logos[1] }}" alt="Llamazul" style="height: 17px">
            </td>
            <td>
                <div class="doc-empresa">MR. DURASOL PERÚ S.A.C. &nbsp;·&nbsp; LLAMAZUL</div>
                <div class="doc-titulo">{{ mb_strtoupper($titulo) }}</div>
            </td>
            <td style="width: 27%">
                <table class="doc-meta">
                    <tr><td class="k">{{ $etiquetaFecha }}</td><td class="v-fecha">{{ $valorFecha }}</td></tr>
                    <tr><td class="k">EMISIÓN</td><td class="v">{{ now()->format('d/m/Y H:i') }}</td></tr>
                </table>
            </td>
        </tr>
    </table>
    <div class="doc-linea"></div><div class="doc-linea2"></div>
</div>
<div class="doc-pie">
    Sistema de gestión comercial Durasol · Llamazul &nbsp;|&nbsp; Emitido por {{ auth()->user()?->name ?? 'sistema' }} el {{ now()->format('d/m/Y') }} a las {{ now()->format('H:i') }}
</div>
