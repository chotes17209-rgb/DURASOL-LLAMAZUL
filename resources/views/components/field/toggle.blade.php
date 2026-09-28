@props(['name', 'label', 'checked' => false, 'hint' => null])
<div {{ $attributes->only('class') }}>
    <input type="hidden" name="{{ $name }}" value="0">
    <label class="inline-flex cursor-pointer items-center gap-2 text-[13px] text-slate-700">
        <input type="checkbox" name="{{ $name }}" value="1" class="form-check" @checked(old($name, $checked))>
        {{ $label }}
    </label>
    @if ($hint)<p class="form-hint">{{ $hint }}</p>@endif
    <p class="form-error hidden" data-error-for="{{ $name }}"></p>
</div>
