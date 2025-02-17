<?php

namespace Botble\Commission\Http\Requests;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Support\Http\Requests\Request;
use Illuminate\Validation\Rule;

class CommissionRequest extends Request
{
    public function rules(): array
    {
        return [
            'driver' => ['required', 'integer', 'max:100', "min:0"],
            'vendor' => ['required', 'integer', 'max:100', "min:0"]
        ];
    }
}
