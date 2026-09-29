{{-- Numeración de páginas: debe ir al final del documento para alcanzar todas las páginas. --}}
<script type="text/php">
    if (isset($pdf)) {
        $font = $fontMetrics->getFont('DejaVu Sans');
        $pdf->page_text($pdf->get_width() - 92, $pdf->get_height() - 21, "Página {PAGE_NUM} de {PAGE_COUNT}", $font, 6.8, [0.42, 0.47, 0.52]);
    }
</script>
