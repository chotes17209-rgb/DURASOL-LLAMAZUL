@if ($paginator->total() > 0)
<nav class="flex flex-col items-center justify-between gap-2 border-t border-line bg-panel px-3 py-2 text-xs sm:flex-row">
    <p class="text-slate-500">Mostrando <b class="text-slate-800">{{ $paginator->firstItem() }}–{{ $paginator->lastItem() }}</b> de <b class="text-slate-800">{{ num($paginator->total()) }}</b> registros</p>
    @if ($paginator->hasPages())
    <div class="flex items-center gap-0.5">
        @if ($paginator->onFirstPage())
            <span class="btn-icon opacity-40"><x-heroicon-o-chevron-left/></span>
        @else
            <a href="{{ $paginator->previousPageUrl() }}" class="btn-icon"><x-heroicon-o-chevron-left/></a>
        @endif
        @foreach ($elements as $element)
            @if (is_string($element))<span class="px-1.5 text-slate-400">…</span>@endif
            @if (is_array($element))
                @foreach ($element as $page => $url)
                    @if ($page == $paginator->currentPage())
                        <span class="inline-flex h-7 min-w-7 items-center justify-center rounded-md border border-brand-800 bg-brand-800 px-1.5 font-semibold text-white">{{ $page }}</span>
                    @else
                        <a href="{{ $url }}" class="inline-flex h-7 min-w-7 items-center justify-center rounded border border-transparent px-1.5 text-slate-600 hover:border-line hover:bg-white">{{ $page }}</a>
                    @endif
                @endforeach
            @endif
        @endforeach
        @if ($paginator->hasMorePages())
            <a href="{{ $paginator->nextPageUrl() }}" class="btn-icon"><x-heroicon-o-chevron-right/></a>
        @else
            <span class="btn-icon opacity-40"><x-heroicon-o-chevron-right/></span>
        @endif
    </div>
    @endif
</nav>
@endif
