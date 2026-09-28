@props(['label', 'value', 'hint' => null, 'total' => false, 'tone' => null])
{{-- Celda de una barra de indicadores (.ledger). --}}
<div {{ $attributes->class(['ledger-cell', 'ledger-total border-r-0' => $total]) }}>
    <dt>{{ $label }}</dt>
    <dd @class(['!text-red-700' => $tone === 'red' && ! $total, '!text-emerald-700' => $tone === 'green' && ! $total])>{{ $value }}</dd>
    @if ($hint)<p>{{ $hint }}</p>@endif
</div>
