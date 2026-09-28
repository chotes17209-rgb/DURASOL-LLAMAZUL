<div class="p-6">
    @if ($audits->isEmpty())
        <x-empty title="Sin registros" icon="clock"/>
    @else
        <ol class="relative space-y-5 border-l border-slate-200 pl-6">
            @foreach ($audits as $audit)
                @include('partials.audit-item', ['audit' => $audit, 'showModule' => true])
            @endforeach
        </ol>
    @endif
</div>
{{ $audits->links() }}
