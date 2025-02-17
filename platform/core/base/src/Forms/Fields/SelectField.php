<?php

namespace Botble\Base\Forms\Fields;

use Botble\Base\Forms\FormField;

class SelectField extends FormField
{
    protected $valueProperty = 'selected';

    protected function getTemplate(): string
    {
        return 'core/base::forms.fields.custom-select';
    }

    public function getDefaults(): array
    {
        return [
            'choices' => [],
            'option_attributes' => [],
            'empty_value' => null,
            'selected' => null,
        ];
    }
}
