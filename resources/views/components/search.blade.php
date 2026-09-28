@props(['placeholder' => 'Buscar...', 'value' => null])
<div {{ $attributes->merge(['class' => 'relative min-w-56 flex-1']) }}>
    <x-heroicon-o-magnifying-glass class="pointer-events-none absolute top-1/2 left-3 h-4 w-4 -translate-y-1/2 text-slate-400"/>
    <input type="search" name="q" value="{{ $value ?? request('q') }}" placeholder="{{ $placeholder }}" class="form-input pl-9">
</div>
