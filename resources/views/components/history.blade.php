@props(['model'])
{{-- Historial (auditoría) de un registro: quién, cuándo y qué cambió. --}}
@php($audits = $model->audits()->with('user')->limit(100)->get())
@if ($audits->isEmpty())
    <x-empty title="Sin historial" text="Este registro todavía no tiene cambios registrados." icon="clock"/>
@else
    <ol class="relative space-y-5 border-l border-slate-200 pl-6">
        @foreach ($audits as $audit)
            @include('partials.audit-item', ['audit' => $audit])
        @endforeach
    </ol>
@endif
