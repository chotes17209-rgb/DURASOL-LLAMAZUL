<x-modal :title="$cuenta->nombreMostrar()" :subtitle="$cuenta->numero" icon="building-library">
    <div x-data="{ tab: 'depositos' }">
        <x-tabs :tabs="['depositos' => 'Últimos depósitos', 'historial' => 'Historial']"/>
        <div x-show="tab === 'depositos'">
            <table class="table table-compact">
                <thead><tr><th>Fecha</th><th>Depositante</th><th>Operación</th><th class="text-right">Monto</th></tr></thead>
                <tbody>
                @forelse ($depositos as $d)
                    <tr><td>{{ fecha($d->fecha) }}</td><td>{{ $d->depositante }}</td><td class="font-mono text-xs">{{ $d->numero_operacion }}</td><td class="text-right">{{ soles($d->monto) }}</td></tr>
                @empty
                    <tr><td colspan="4" class="text-center text-slate-400">Sin depósitos.</td></tr>
                @endforelse
                </tbody>
            </table>
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$cuenta"/></div>
    </div>
</x-modal>
