@if ($paginator->total() > 0)
    <nav class="admin-pagination-bar" aria-label="Navigasi halaman">
        <p class="admin-pagination-summary tabular-nums">
            Menampilkan {{ number_format($paginator->firstItem() ?? 0, 0, ',', '.') }}-{{ number_format($paginator->lastItem() ?? 0, 0, ',', '.') }}
            dari {{ number_format($paginator->total(), 0, ',', '.') }} data
        </p>

        @if ($paginator->hasPages())
            <ul class="admin-pagination-list">
                @if ($paginator->onFirstPage())
                    <li>
                        <span class="admin-page-link is-disabled" aria-disabled="true" aria-label="Halaman sebelumnya">
                            <x-icon name="chevron-left" size="1em" />
                            <span>Sebelumnya</span>
                        </span>
                    </li>
                @else
                    <li>
                        <a class="admin-page-link" href="{{ $paginator->previousPageUrl() }}" rel="prev" aria-label="Halaman sebelumnya">
                            <x-icon name="chevron-left" size="1em" />
                            <span>Sebelumnya</span>
                        </a>
                    </li>
                @endif

                @foreach ($elements as $element)
                    @if (is_string($element))
                        <li><span class="admin-page-link is-disabled" aria-hidden="true">{{ $element }}</span></li>
                    @endif

                    @if (is_array($element))
                        @foreach ($element as $page => $url)
                            @if ($page == $paginator->currentPage())
                                <li>
                                    <span class="admin-page-link is-current tabular-nums" aria-current="page">{{ $page }}</span>
                                </li>
                            @else
                                <li>
                                    <a class="admin-page-link tabular-nums" href="{{ $url }}" aria-label="Halaman {{ $page }}">{{ $page }}</a>
                                </li>
                            @endif
                        @endforeach
                    @endif
                @endforeach

                @if ($paginator->hasMorePages())
                    <li>
                        <a class="admin-page-link" href="{{ $paginator->nextPageUrl() }}" rel="next" aria-label="Halaman berikutnya">
                            <span>Berikutnya</span>
                            <x-icon name="chevron-right" size="1em" />
                        </a>
                    </li>
                @else
                    <li>
                        <span class="admin-page-link is-disabled" aria-disabled="true" aria-label="Halaman berikutnya">
                            <span>Berikutnya</span>
                            <x-icon name="chevron-right" size="1em" />
                        </span>
                    </li>
                @endif
            </ul>
        @endif
    </nav>
@endif
