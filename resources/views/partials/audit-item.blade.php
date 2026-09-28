<li class="relative">
    <span @class([
        'absolute -left-[31px] top-1 flex h-4 w-4 items-center justify-center rounded-full ring-4 ring-white',
        'bg-emerald-600' => $audit->eventColor() === 'emerald',
        'bg-brand-600' => $audit->eventColor() === 'sky',
        'bg-red-600' => $audit->eventColor() === 'rose',
        'bg-amber-500' => $audit->eventColor() === 'amber',
        'bg-brand-900' => $audit->eventColor() === 'violet',
        'bg-slate-400' => $audit->eventColor() === 'slate',
    ])></span>
    <div class="flex flex-wrap items-center gap-x-2 gap-y-1 text-sm">
        <span class="font-semibold text-slate-900">{{ $audit->user?->name ?? 'Sistema' }}</span>
        <span class="text-slate-500">{{ mb_strtolower($audit->eventLabel()) }}</span>
        @if (! empty($showModule))
            <x-badge color="slate">{{ $audit->moduleLabel() }}</x-badge>
            <span class="font-medium text-slate-700">{{ $audit->auditable?->auditLabel() ?? ($audit->auditable_id ? '#'.$audit->auditable_id : '') }}</span>
        @endif
        <span class="text-xs text-slate-400" title="{{ $audit->created_at->format('d/m/Y H:i:s') }}">· {{ $audit->created_at->diffForHumans() }} ({{ $audit->created_at->format('d/m/Y H:i') }})</span>
    </div>
    @if ($audit->description)
        <p class="mt-1 text-sm text-slate-600">{{ $audit->description }}</p>
    @endif
    @php($changes = $audit->event === 'updated' ? array_keys($audit->new_values ?? []) : [])
    @if ($changes)
        <div class="mt-2 overflow-hidden rounded border border-line">
            <table class="w-full text-xs">
                <thead class="bg-slate-50 text-slate-500"><tr><th class="px-3 py-1.5 text-left font-semibold">Campo</th><th class="px-3 py-1.5 text-left font-semibold">Antes</th><th class="px-3 py-1.5 text-left font-semibold">Después</th></tr></thead>
                <tbody>
                @foreach ($changes as $field)
                    <tr class="border-t border-slate-100">
                        <td class="px-3 py-1.5 font-medium text-slate-600">{{ str_replace('_', ' ', $field) }}</td>
                        <td class="px-3 py-1.5 text-red-700 line-through decoration-red-300">{{ is_array($audit->old_values[$field] ?? null) ? json_encode($audit->old_values[$field]) : ($audit->old_values[$field] ?? '—') }}</td>
                        <td class="px-3 py-1.5 text-emerald-700">{{ is_array($audit->new_values[$field]) ? json_encode($audit->new_values[$field]) : ($audit->new_values[$field] ?? '—') }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </div>
    @elseif (in_array($audit->event, ['created', 'deleted']) && ($audit->new_values || $audit->old_values))
        <details class="mt-1 text-xs text-slate-500">
            <summary class="cursor-pointer select-none font-medium text-brand-700">Ver datos</summary>
            <dl class="mt-2 grid grid-cols-2 gap-x-4 gap-y-1 rounded bg-slate-50 p-3 sm:grid-cols-3">
                @foreach (($audit->new_values ?: $audit->old_values) as $field => $value)
                    <div><dt class="text-slate-400">{{ str_replace('_', ' ', $field) }}</dt><dd class="font-medium text-slate-700">{{ is_array($value) ? json_encode($value) : ($value ?? '—') }}</dd></div>
                @endforeach
            </dl>
        </details>
    @endif
</li>
