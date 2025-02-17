<?php
namespace Botble\Ecommerce\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateCustomerAddressRequest extends FormRequest
{
    public function authorize()
    {
        return true; // Ensure the user is authorized
    }

    public function rules()
    {
        return [
           // 'id' => 'sometimes|required|integer',
            'name' => 'sometimes|required|string|max:255',
            'email' => 'sometimes|required|email|max:255',
            'phone' => 'sometimes|required|string|max:20',
            'country' => 'sometimes|required|string|max:100',
            'state' => 'sometimes|required|string|max:100',
            'city' => 'sometimes|required|string|max:100',
            'address' => 'sometimes|required|string|max:255',
            'zip_code' => 'sometimes|required|string|max:20',
            'is_default' => 'sometimes|required|boolean',
        ];
    }

    public function messages()
    {
        return [
            'customer_id.exists' => 'The specified customer does not exist.',
        ];
    }
}
