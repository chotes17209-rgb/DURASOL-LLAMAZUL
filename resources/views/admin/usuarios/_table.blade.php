<div class="table-wrap">
    <table class="table">
        <thead><tr><th>Usuario</th><th>Correo</th><th>Rol</th><th>Último acceso</th><th>Estado</th><th></th></tr></thead>
        <tbody>
        @forelse ($usuarios as $u)
            <tr>
                <td>
                    <div class="flex items-center gap-3">
                        <div class="flex h-9 w-9 items-center justify-center rounded bg-brand-100 text-xs font-bold text-brand-700">{{ $u->initials() }}</div>
                        <div><p class="font-semibold text-slate-900">{{ $u->name }}</p><p class="font-mono text-xs text-slate-500">{{ $u->username }}</p></div>
                    </div>
                </td>
                <td>{{ $u->email }}</td>
                <td><x-badge color="blue">{{ $u->role->label() }}</x-badge></td>
                <td class="text-xs text-slate-500">{{ $u->last_login_at?->diffForHumans() ?? 'Nunca' }}</td>
                <td><x-badge :color="$u->active ? 'green' : 'red'">{{ $u->active ? 'Activo' : 'Inactivo' }}</x-badge></td>
                <td><x-row-actions size="md" :show="route('usuarios.show', $u)" :edit="route('usuarios.edit', $u)" :delete="$u->is(auth()->user()) ? null : route('usuarios.destroy', $u)"/></td>
            </tr>
        @empty
            <tr><td colspan="6"><x-empty/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $usuarios->links() }}
