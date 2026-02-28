{{-- resources/views/vendor/pagination/p360.blade.php --}}
@if ($paginator->hasPages())
  <nav class="p360-pagination" role="navigation" aria-label="Paginación">

    {{-- Previous --}}
    @if ($paginator->onFirstPage())
      <span class="p360-page p360-disabled" aria-disabled="true" aria-label="@lang('pagination.previous')">
        <span class="p360-ic" aria-hidden="true">‹</span>
        <span class="p360-lbl">Anterior</span>
      </span>
    @else
      <a class="p360-page" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="@lang('pagination.previous')">
        <span class="p360-ic" aria-hidden="true">‹</span>
        <span class="p360-lbl">Anterior</span>
      </a>
    @endif

    {{-- Numbers --}}
    <div class="p360-pages" role="list">
      @foreach ($elements as $element)
        @if (is_string($element))
          <span class="p360-page p360-dots" aria-disabled="true">{{ $element }}</span>
        @endif

        @if (is_array($element))
          @foreach ($element as $page => $url)
            @if ($page == $paginator->currentPage())
              <span class="p360-page p360-active" aria-current="page">{{ $page }}</span>
            @else
              <a class="p360-page" href="{{ $url }}" aria-label="Ir a la página {{ $page }}">{{ $page }}</a>
            @endif
          @endforeach
        @endif
      @endforeach
    </div>

    {{-- Next --}}
    @if ($paginator->hasMorePages())
      <a class="p360-page" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="@lang('pagination.next')">
        <span class="p360-lbl">Siguiente</span>
        <span class="p360-ic" aria-hidden="true">›</span>
      </a>
    @else
      <span class="p360-page p360-disabled" aria-disabled="true" aria-label="@lang('pagination.next')">
        <span class="p360-lbl">Siguiente</span>
        <span class="p360-ic" aria-hidden="true">›</span>
      </span>
    @endif

  </nav>
@endif