@props(['name', 'label' => null, 'type' => 'text', 'value' => null, 'hint' => null, 'required' => false, 'prefix' => null])
@php($id = 'f_'.str_replace(['[', ']', '.'], '_', $name).'_'.uniqid())
<div {{ $attributes->only('class')->merge(['class' => '']) }}>
    @if ($label)
        <label for="{{ $id }}" class="form-label">{{ $label }} @if($required)<span class="text-red-700">*</span>@endif</label>
    @endif
    <div class="relative">
        @if ($prefix)
            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm font-medium text-slate-400">{{ $prefix }}</span>
        @endif
        <input id="{{ $id }}" type="{{ $type }}" name="{{ $name }}" value="{{ old($name, $value) }}" @if($required) required @endif
               {{ $attributes->except('class')->merge(['class' => 'form-input'.($prefix ? ' pl-9' : '')]) }}>
    </div>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    <p class="form-error hidden" data-error-for="{{ $name }}"></p>
</div>
