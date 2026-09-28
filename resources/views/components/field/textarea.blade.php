@props(['name', 'label' => null, 'value' => null, 'rows' => 3, 'required' => false])
<div {{ $attributes->only('class') }}>
    @if ($label)
        <label class="form-label">{{ $label }} @if($required)<span class="text-rose-500">*</span>@endif</label>
    @endif
    <textarea name="{{ $name }}" rows="{{ $rows }}" @if($required) required @endif {{ $attributes->except('class')->merge(['class' => 'form-input']) }}>{{ old($name, $value) }}</textarea>
    <p class="form-error hidden" data-error-for="{{ $name }}"></p>
</div>
