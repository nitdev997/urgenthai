<div class="simple-pagination d-flex align-items-center">
    <p class="m-0 text-secondary">
        {!! __('Showing') !!}
        <span>{{ $paginator->firstItem() }}</span>
        {!! __('to') !!}
        <span>{{ $paginator->lastItem() }}</span>
        {!! __('of') !!}
        <span>{{ $paginator->total() }}</span>
        {!! __('results') !!}
    </p>

    @if ($paginator->hasPages())
        <ul class="m-0 pagination ms-auto">
            <li @class(['page-item', 'disabled' => $paginator->onFirstPage()])>
                @if ($paginator->onFirstPage())
                    <span
                        class="page-link"
                        aria-disabled="true"
                    >
                        <x-core::icon name="ti ti-chevron-left" />
                    </span>
                @else
                    <a
                        class="page-link"
                        href="{{ $paginator->previousPageUrl() }}"
                        tabindex="-1"
                        aria-disabled="true"
                    >
                        <x-core::icon name="ti ti-chevron-left" />
                    </a>
                @endif
            </li>

            <li @class(['page-item', 'disabled' => !$paginator->hasMorePages()])>
                @if ($paginator->hasMorePages())
                    <a
                        class="page-link"
                        href="{{ $paginator->nextPageUrl() }}"
                    >
                        <x-core::icon name="ti ti-chevron-right" />
                    </a>
                @else
                    <span
                        class="page-link"
                        aria-disabled="true"
                    >
                        <x-core::icon name="ti ti-chevron-right" />
                    </span>
                @endif
            </li>
        </ul>
    @endif
</div>
