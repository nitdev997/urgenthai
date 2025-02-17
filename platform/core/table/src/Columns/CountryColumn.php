<?php

namespace Botble\Table\Columns;

use Botble\Base\Facades\Html;
use Botble\Table\Columns\Concerns\HasLink;
use Botble\Table\Contracts\FormattedColumn as FormattedColumnContract;

class CountryColumn extends FormattedColumn implements FormattedColumnContract
{
    use HasLink;

    public static function make(array|string $data = [], string $name = ''): static
    {
        return parent::make($data ?: 'country', $name)
            ->title(trans('Country'))
            ->alignStart();
    }
}
