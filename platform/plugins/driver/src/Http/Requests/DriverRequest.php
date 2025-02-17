<?php

namespace Botble\Driver\Http\Requests;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Support\Http\Requests\Request;
use Illuminate\Validation\Rule;

class DriverRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:20'],
            'email' => 'required|string|email|max:255',
            'phone' => 'required|string|max:15',
            'address' => 'required|string|max:255',
            'dl_number' => 'required|string',
            'country' => 'required|string|max:10',
            'status' => Rule::in(BaseStatusEnum::values()),
        ];
    }
}
