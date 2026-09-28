@if ($paginator->hasPages() || $paginator->total() > 0)
<nav class="flex flex-col items-center justify-between gap-3 border-t border-slate-100 px-5 py-3 text-sm sm:flex-row">
    <p class="text-xs text-slate-500">
        Mostrando <span class="font-semibold text-slate-700">{{ $paginator->firstItem() ?? 0 }}</span>–<span class="font-semibold text-slate-700">{{ $paginator->lastItem() ?? 0 }}</span>
        de <span class="font-semibold text-slate-700">{{ num($paginator->total()) }}</span> registros
    </p>
    @if ($paginator->hasPages())
    <div class="flex items-center gap-1">
        @if ($paginator->onFirstPage())
            <span class="btn-icon opacity-40"><x-heroicon-o-chevron-left class="h-4 w-4"/></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn-icon"><x-heroicon-o-chevron-left class="h-4 w-4"/></a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))
                <span class="px-2 text-slate-400">…</span>
            @endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg bg-brand-600 px-2 text-xs font-bold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="inline-flex h-8 min-w-8 items-center justify-center rounded-lg px-2 text-xs font-semibold text-slate-600 hover:bg-slate-100">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn-icon"><x-heroicon-o-chevron-right class="h-4 w-4"/></a>
        @else
            <span class="btn-icon opacity-40"><x-heroicon-o-chevron-right class="h-4 w-4"/></span>
        @endif
    </div>
    @endif
</nav>
@endif
