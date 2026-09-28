{{-- Navegación del módulo de caja: las cuatro vistas comparten esta barra. --}}
<nav class="segmented mb-4 max-w-full overflow-x-auto">
    @foreach ([
        ['caja.index', 'Caja general', 'banknotes', ['caja.index']],
        ['caja.chica.index', 'Caja chica', 'wallet', ['caja.chica.*']],
        ['caja.arqueos.index', 'Arqueo de efectivo', 'calculator', ['caja.arqueos.*']],
        ['caja.depositos.index', 'Depósitos bancarios', 'building-library', ['caja.depositos.*']],
    ] as [$ruta, $texto, $icono, $match])
        <a href="{{ route($ruta) }}" @class(['inline-flex items-center gap-2 whitespace-nowrap', 'active' => request()->routeIs(...$match)])>
            <x-dynamic-component :component="'heroicon-o-'.$icono" class="h-4 w-4"/> {{ $texto }}
        </a>
    @endforeach
</nav>
