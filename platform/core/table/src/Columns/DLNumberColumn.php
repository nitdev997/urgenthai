<?php

namespace Botble\Table\Columns;

use Botble\Base\Facades\Html;
use Botble\Table\Columns\Concerns\HasLink;
use Botble\Table\Contracts\FormattedColumn as FormattedColumnContract;

class DLNumberColumn extends FormattedColumn implements FormattedColumnContract
{
    use HasLink;

    public static function make(array|string $data = [], string $name = ''): static
    {
        return parent::make($data ?: 'dl_number', $name)
            ->title(trans('DL Number'))
            ->alignStart();
    }
}
