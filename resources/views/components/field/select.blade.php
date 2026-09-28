@props(['name', 'label' => null, 'options' => [], 'selected' => null, 'placeholder' => 'Seleccionar...', 'required' => false, 'hint' => null, 'tom' => false, 'empty' => true])
{{-- $options: [valor => etiqueta]. Con :tom="true" se vuelve buscable (Tom Select). --}}
@php
    $id = 'f_'.str_replace(['[', ']', '.'], '_', $name).'_'.uniqid();
    $current = old($name, $selected instanceof \BackedEnum ? $selected->value : $selected);
    $currentValues = is_array($current) ? array_map('strval', $current) : [(string) $current];
@endphp
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if($required)<span class="text-rose-500">*</span>@endif</label>
    @endif
    <select id="{{ $id }}" name="{{ $name }}" @if($tom) data-tom @endif placeholder="{{ $placeholder }}" @if($required) required @endif
            {{ $attributes->except('class')->merge(['class' => 'form-input']) }}>
        @if ($empty)
            <option value="">{{ $placeholder }}</option>
        @endif
        @foreach ($options as $value => $text)
            <option value="{{ $value }}" @selected(in_array((string) $value, $currentValues, true))>{{ $text }}</option>
        @endforeach
    </select>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    <p class="form-error hidden" data-error-for="{{ $name }}"></p>
</div>
