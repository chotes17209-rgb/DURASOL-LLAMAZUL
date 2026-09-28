@props(['tabs'])
{{-- Pestañas; el contenedor padre debe tener x-data="{ tab: '...' }". --}}
<div {{ $attributes->merge(['class' => 'tabs mb-4']) }}>
    @foreach ($tabs as $key => $label)
        <button type="button" class="tab" :class="tab === '{{ $key }}' && 'active'" @click="tab = '{{ $key }}'">{{ $label }}</button>
    @endforeach
</div>
