<div
    @if(! $width && ! $height)
        style="position: relative; display: block; height: 0; padding-bottom: 56.25%; overflow: hidden;"
    @else
        style="margin-bottom: 20px;"
    @endif
>
        <iframe
            src="https://maps.google.com/maps?q={{ addslashes($address) }}%20&t=&z=13&ie=UTF8&iwloc=&output=embed"
            @if(! $width && ! $height)
                style="position: absolute; top: 0; bottom: 0; left: 0; width: 100%; height: 100%; border: 0;"
            @endif
            @if ($height)
                height="{{ $height }}"
            @endif

            @if ($width)
                width="{{ $width }}"
            @endif

            frameborder="0"
            scrolling="no"
            marginheight="0"
            marginwidth="0"
        ></iframe>
</div>
