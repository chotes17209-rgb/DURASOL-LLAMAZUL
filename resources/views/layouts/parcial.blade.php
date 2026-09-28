{{-- Ficha o formulario abierto directamente por su dirección: se muestra como página. --}}
<x-layouts.app :title="$titulo ?? 'Detalle'">
    <x-slot:actions>
        <button type="button" class="btn btn-secondary" onclick="history.length > 1 ? history.back() : (location.href = '{{ route('dashboard') }}')"><x-heroicon-o-arrow-left/> Volver</button>
    </x-slot:actions>
    <div class="app-modal mx-auto max-w-5xl" data-modal-content data-vista-parcial>
        {!! $contenido !!}
    </div>
</x-layouts.app>
