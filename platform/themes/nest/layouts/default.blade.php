{!! Theme::partial('header') !!}

<style>
    header.header-area, footer.main, .footer-mobile{display: none !important;}
</style>

@php
    $layoutData = Theme::get('layout-data') ?: [];
@endphp
<main class="main {{ Arr::get($layoutData, 'main-class', '') }}" id="main-section">
    @if (Theme::get('hasBreadcrumb', true))
        {!! Theme::partial('breadcrumb') !!}
    @endif

    @if (Arr::get($layoutData, 'is-wrap-container', false))
        <div class="page-content {{ Arr::get($layoutData, 'wrap-container-class', '') }}">
    @endif
        <div class="container">
            {!! Theme::content() !!}
        </div>
    @if (Arr::get($layoutData, 'is-wrap-container', false))
        </div>
    @endif
</main>

{!! Theme::partial('footer') !!}
