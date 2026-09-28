<x-modal :title="'Historial de precios · '.$cliente->nombre" :subtitle="'Código '.$cliente->codigo" icon="tag">
    @include('precios.venta._timeline', ['historial' => $historial, 'productos' => $productos])
</x-modal>
