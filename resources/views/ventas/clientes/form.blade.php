<x-form-modal :title="$cliente->exists ? 'Editar cliente' : 'Nuevo cliente'" :subtitle="$cliente->exists ? $cliente->codigo.' · '.$cliente->nombre : null" icon="user"
              :action="$cliente->exists ? route('clientes.update', $cliente) : route('clientes.store')" :method="$cliente->exists ? 'PUT' : 'POST'">
    <div class="grid gap-4 sm:grid-cols-4">
        <x-field.input name="codigo" type="number" label="Código" :value="$cliente->codigo" min="1"/>
        <x-field.input name="nombre" label="Nombres y apellidos / razón social" :value="$cliente->nombre" required class="sm:col-span-3"/>
        <x-field.input name="conocido_como" label="Conocido como" :value="$cliente->conocido_como" class="sm:col-span-2"/>
        <x-field.input name="documento" label="DNI / RUC" :value="$cliente->documento" inputmode="numeric" maxlength="11"/>
        <x-field.input name="telefono" label="Teléfono" :value="$cliente->telefono"/>
        <x-field.input name="direccion" label="Dirección" :value="$cliente->direccion" class="sm:col-span-3"/>
        <x-field.input name="zona" label="Zona / distrito" :value="$cliente->zona"/>
        <x-field.input name="correo" type="email" label="Correo" :value="$cliente->correo" class="sm:col-span-2"/>
        <x-field.select name="chofer_id" label="Chofer responsable" :options="$choferes" :selected="$cliente->chofer_id" placeholder="— Sin asignar —" tom/>
        <x-field.select name="tipo" label="Tipo" :options="\App\Models\Cliente::TIPOS" :selected="$cliente->tipo" :empty="false"/>
        <x-field.input name="limite_credito" type="number" step="0.01" min="0" label="Límite de crédito" prefix="S/" :value="$cliente->limite_credito ?? 0"/>
        <x-field.textarea name="observaciones" label="Observaciones" :value="$cliente->observaciones" rows="2" class="sm:col-span-3"/>
    </div>

    <div class="mt-6 rounded-2xl bg-slate-50 p-4 ring-1 ring-slate-200">
        <div class="mb-3 flex items-center justify-between">
            <p class="text-sm font-semibold text-slate-900">Precios de venta asignados</p>
            <p class="text-xs text-slate-500">{{ $cliente->exists ? 'Si cambias un precio se guarda como nuevo en el historial (vigente desde hoy).' : 'Deja vacío lo que no le vendes.' }}</p>
        </div>
        <div class="grid gap-3 sm:grid-cols-4">
            @foreach ($productos as $p)
                <x-field.input :name="'precios['.$p->id.']'" type="number" step="0.01" min="0" :label="$p->codigo" prefix="S/" :value="$vigentes[$p->id] ?? null"/>
            @endforeach
        </div>
    </div>
    <div class="mt-4"><x-field.toggle name="activo" label="Cliente activo" :checked="$cliente->activo"/></div>
</x-form-modal>
