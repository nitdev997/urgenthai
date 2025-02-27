@php
$name = Str::contains($name = $menu['name'], '::') ?  BaseHelper::clean(trans($name)) : $name;
@endphp
@if($name != 'Blog' && $name != 'Contact' && $name != 'Simple Sliders' && $name != 'FAQs' && $name != 'Newsletters' && $name != 'Locations' && $name != 'Plugins' && $name != 'Platform Administration' && $name != 'Report' && $name != 'Order returns' && $name != 'Reviews' && $name != 'Discounts' && $name != 'Pages' && $name != 'Reports' && $name != 'Withdrawals' && $name != 'Themes' && $name != 'Widgets' && $name != 'Theme Options' && $name != 'Custom HTML')
<a
    @class([
        'nav-link' => $isNav = $isNav ?? true,
        'dropdown-item' => !$isNav,
        'dropdown-toggle' => $hasChildren,
        'nav-priority-' . $menu['priority'],
        'active show' => $menu['active'],
    ])
    href="{{ $hasChildren ? "#$menu[id]" : $menu['url'] }}"
    id="{{ $menu['id'] }}"
    @if ($hasChildren)
        data-bs-toggle="dropdown"
        data-bs-auto-close="{{ $autoClose ?? 'false' }}"
        role="button"
        aria-expanded="{{ $menu['active'] ? 'true' : 'false' }}"
    @endif
    title="{{ $menu['title'] ?? $name }}"
>
    @if (AdminAppearance::showMenuItemIcon() && $menu['icon'] !== false)
        <span class="nav-link-icon d-md-none d-lg-inline-block">
            <x-core::icon :name="$menu['icon'] ?: 'ti ti-point'" />
        </span>
    @endif

    <span @class(['nav-link-title text-truncate'])>
        {!! $name !!}
        {!! apply_filters(BASE_FILTER_APPEND_MENU_NAME, null, $menu['id']) !!}
    </span>
</a>
@endif
