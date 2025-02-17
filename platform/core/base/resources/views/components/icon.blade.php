@props(['name', 'size' => null, 'wrapper' => true])

@php
    $class = Arr::toCssClasses(['icon', $name, "icon-$size" => $size]);
@endphp

@if (str_contains($name, 'ti ti-') && $name = str_replace('ti ti-', '', $name))
    @if ($wrapper)
        <span {{ $attributes->class(['icon-tabler-wrapper', "icon-$size" => $size]) }}>
            @include('core/base::components.icons.' . $name)
        </span>
    @else
        @include('core/base::components.icons.' . $name)
    @endif
@else
    <i {{ $attributes->class($class) }}></i>
@endif
