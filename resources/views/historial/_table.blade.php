@php
    $colores = ['emerald' => 'badge-green', 'sky' => 'badge-blue', 'rose' => 'badge-red', 'amber' => 'badge-amber', 'violet' => 'badge-violet', 'slate' => 'badge-slate'];
    $valor = fn ($v) => is_array($v) ? json_encode($v, JSON_UNESCAPED_UNICODE) : ($v === null || $v === '' ? '—' : (string) $v);
@endphp
<div class="table-wrap">
    <table class="table table-compact">
        <thead><tr><th class="w-36">Fecha y hora</th><th class="w-44">Usuario</th><th class="w-32">Acción</th><th class="w-36">Módulo</th><th>Registro y detalle</th></tr></thead>
        <tbody>
        @forelse ($audits as $audit)
            @php($cambios = $audit->event === 'updated' ? array_keys($audit->new_values ?? []) : [])
            <tr class="align-top">
                <td class="whitespace-nowrap">
                    <p class="font-medium text-slate-800">{{ $audit->created_at->format('d/m/Y') }}</p>
                    <p class="text-[12px] text-slate-500">{{ $audit->created_at->format('H:i:s') }} · {{ $audit->created_at->diffForHumans() }}</p>
                </td>
                <td>
                    <p class="font-semibold text-slate-900">{{ $audit->user?->name ?? 'Sistema' }}</p>
                    @if ($audit->ip_address ?? null)<p class="font-mono text-[11.5px] text-slate-400">{{ $audit->ip_address }}</p>@endif
                </td>
                <td><span class="badge {{ $colores[$audit->eventColor()] ?? 'badge-slate' }}">{{ $audit->eventLabel() }}</span></td>
                <td class="text-slate-700">{{ $audit->moduleLabel() }}</td>
                <td>
                    <p class="font-medium text-slate-900">{{ $audit->auditable?->auditLabel() ?? ($audit->auditable_id ? '#'.$audit->auditable_id : '') }}</p>
                    @if ($audit->description)<p class="text-[12px] text-slate-600">{{ $audit->description }}</p>@endif
                    @if ($cambios)
                        <table class="mt-1.5 w-full max-w-3xl border border-line-soft text-[12px]">
                            @foreach ($cambios as $campo)
                                <tr class="border-t border-line-soft first:border-t-0">
                                    <td class="w-40 bg-panel px-2 py-1 font-medium text-slate-600">{{ str_replace('_', ' ', $campo) }}</td>
                                    <td class="px-2 py-1 text-red-700 line-through decoration-red-300">{{ $valor($audit->old_values[$campo] ?? null) }}</td>
                                    <td class="px-2 py-1 text-emerald-800">{{ $valor($audit->new_values[$campo] ?? null) }}</td>
                                </tr>
                            @endforeach
                        </table>
                    @elseif (in_array($audit->event, ['created', 'deleted']) && ($audit->new_values || $audit->old_values))
                        <details class="mt-1 text-[12px]">
                            <summary class="cursor-pointer font-medium text-brand-700 select-none">Ver datos registrados</summary>
                            <dl class="dl-grid mt-1.5 max-w-3xl">
                                @foreach (($audit->new_values ?: $audit->old_values) as $campo => $v)
                                    <div><dt>{{ str_replace('_', ' ', $campo) }}</dt><dd>{{ $valor($v) }}</dd></div>
                                @endforeach
                            </dl>
                        </details>
                    @endif
                </td>
            </tr>
        @empty
            <tr><td colspan="5"><x-empty title="Sin registros" text="No hay acciones registradas con estos filtros." icon="clock"/></td></tr>
        @endforelse
        </tbody>
    </table>
</div>
{{ $audits->links() }}
