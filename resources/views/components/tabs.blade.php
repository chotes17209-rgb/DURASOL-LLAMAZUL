@props(['tabs'])
{{-- Botones de pestañas; el contenedor padre debe tener x-data="{ tab: '...' }". --}}
<div {{ $attributes->merge(['class' => 'tabs mb-5']) }}>
    @foreach ($tabs as $key => $label)
        <button type="button" class="tab" :class="tab === '{{ $key }}' && 'active'" @click="tab = '{{ $key }}'">{{ $label }}</button>
    @endforeach
</div>
