@props(['name', 'label', 'checked' => false, 'hint' => null])
<div {{ $attributes->only('class') }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="inline-flex cursor-pointer items-center gap-3">
        <input type="checkbox" name="{{ $name }}" value="1" class="peer sr-only" @checked(old($name, $checked))>
        <span class="relative h-6 w-11 rounded-full bg-slate-200 transition after:absolute after:top-0.5 after:left-0.5 after:h-5 after:w-5 after:rounded-full after:bg-white after:shadow after:transition peer-checked:bg-brand-600 peer-checked:after:translate-x-5"></span>
        <span class="text-sm font-medium text-slate-700">{{ $label }}</span>
    </label>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    <p class="form-error hidden" data-error-for="{{ $name }}"></p>
</div>
