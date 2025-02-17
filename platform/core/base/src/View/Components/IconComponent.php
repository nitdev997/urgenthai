<?php

namespace Botble\Base\View\Components;

use Closure;
use Illuminate\Contracts\View\View;
use Illuminate\View\Component;

class IconComponent extends Component
{
    public function __construct(
        public string $name,
        public string|null $size = null
    ) {
    }

    public function render(): View|Closure|string
    {
        return $this->view('core/base::components.icon');
    }
}
