@props(['title', 'action', 'method' => 'POST', 'subtitle' => null, 'icon' => null, 'submit' => 'Guardar', 'confirm' => null])
{{-- Formulario dentro de un modal que se envía por AJAX (ver resources/js/app.js). --}}
<form method="POST" action="{{ $action }}" data-ajax @if($confirm) data-confirm="{{ $confirm }}" @endif enctype="multipart/form-data" autocomplete="off">
    @csrf
    @if (strtoupper($method) !== 'POST')
        <input type="hidden" name="_method" value="{{ strtoupper($method) }}">
    @endif
    <x-modal :title="$title" :subtitle="$subtitle" {{ $attributes }}>
        {{ $slot }}
        <x-slot:footer>
            <button type="button" class="btn btn-secondary" data-modal-close>Cancelar</button>
            <button type="submit" class="btn btn-primary">{{ $submit }}</button>
        </x-slot:footer>
    </x-modal>
</form>
