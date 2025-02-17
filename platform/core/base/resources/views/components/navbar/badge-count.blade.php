@props([
    'class',
])

<span {{ $attributes->merge(['class' => 'badge badge-sm bg-primary text-primary-fg badge-pill menu-item-count ' . $class]) }} style="display: none"></span>
