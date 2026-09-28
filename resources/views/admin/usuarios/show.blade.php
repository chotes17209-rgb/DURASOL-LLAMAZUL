<x-modal :title="$usuario->name" :subtitle="$usuario->role->label()" icon="user">
    <div x-data="{ tab: 'detalle' }">
        <x-tabs :tabs="['detalle' => 'Detalle', 'actividad' => 'Actividad reciente', 'historial' => 'Cambios al usuario']"/>
        <div x-show="tab === 'detalle'">
            <dl class="dl-grid">
                <div><dt>Usuario</dt><dd class="font-mono">{{ $usuario->username }}</dd></div>
                <div><dt>Correo</dt><dd>{{ $usuario->email }}</dd></div>
                <div><dt>Rol</dt><dd>{{ $usuario->role->label() }}</dd></div>
                <div><dt>Estado</dt><dd>{{ $usuario->active ? 'Activo' : 'Inactivo' }}</dd></div>
                <div><dt>Último acceso</dt><dd>{{ $usuario->last_login_at?->format('d/m/Y H:i') ?? 'Nunca' }}</dd></div>
                <div><dt>Creado</dt><dd>{{ $usuario->created_at?->format('d/m/Y') }}</dd></div>
            </dl>
        </div>
        <div x-show="tab === 'actividad'" x-cloak>
            @if ($actividad->isEmpty())
                <x-empty title="Sin actividad" icon="clock"/>
            @else
                <ol class="relative space-y-5 border-l border-slate-200 pl-6">
                    @foreach ($actividad as $audit)
                        @include('partials.audit-item', ['audit' => $audit, 'showModule' => true])
                    @endforeach
                </ol>
            @endif
        </div>
        <div x-show="tab === 'historial'" x-cloak><x-history :model="$usuario"/></div>
    </div>
</x-modal>
