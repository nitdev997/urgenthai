<?php

namespace Botble\Ecommerce\Http\Controllers\API;

use Botble\Base\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Botble\Ecommerce\Http\Requests\UpdateCustomerAddressRequest;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Illuminate\Support\Facades\Auth;
use Botble\Ecommerce\Models\Customer;
use Illuminate\Support\Facades\Validator;
use Botble\Ecommerce\Models\Address;
use Botble\Ecommerce\Http\Resources\CustomerAddressResource;

class AddressController extends BaseController
{
    /**
     * Create Address
     *
     * @bodyParam name string required Name of User.
     * @bodyParam phone string required phone of User.
     * @bodyParam email string required email of User.
     * @bodyParam country string required country of User.
     * @bodyParam state string required state of User.
     * @bodyParam city string required city of User.
     * @bodyParam address string required address of User.
     * @bodyParam zip_code string required zip_code of User.
     * @bodyParam is_default number required is default address of User.
     *
     * @group Address Book 
     */
    public function createCustomerAddress(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'name' => 'required|string|max:255',
                'email' => 'required|email:rfc,dns|max:255',
                'phone' => 'required|digits:10|regex:/^\+?[91]?\d{10}$/',
                'country' => 'required|string|max:255',
                'state' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'zip_code' => 'nullable|regex:/^\d{6}$/',
                'is_default' => 'sometimes|boolean',
                'lat' => 'required|string',
                'long' => 'required|string',
            ],
            [
                'phone.required' => 'The phone number is required.',
                'phone.digits' => 'Please enter a valid phone number.',
                'phone.regex' => 'Please enter a valid phone number.',
                'zip_code.regex' => 'Please enter a valid zip code.',
            ]
        );

        $customer = auth()->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated customer found.',
            ], 401);
        }

        if (isset($validated['is_default']) && $validated['is_default'] == 1) {
            Address::where('customer_id', $customer->id)->update(['is_default' => 0]);
        }

        $address = Address::create([
            'customer_id' => $customer->id,
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'],
            'country' => $validated['country'],
            'state' => $validated['state'],
            'city' => $validated['city'],
            'address' => $validated['address'],
            'zip_code' => $validated['zip_code'],
            'is_default' => $validated['is_default'] ?? 0,
            'lat' => $validated['lat'],
            'long' => $validated['long'],
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Customer address created successfully.',
        ], 201);
    }

    /**
     * Edit Address By ID
     * @bodyParam name string required Name of User.
     * @bodyParam phone string required phone of User.
     * @bodyParam email string required email of User.
     * @bodyParam country string required country of User.
     * @bodyParam state string required state of User.
     * @bodyParam city string required city of User.
     * @bodyParam address string required address of User.
     * @bodyParam zip_code string required zip_code of User.
     * @bodyParam is_default number required is default address of User.
     *
     * @group Address Book
     */
    public function editCustomerAddress(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required|integer',
        ]);
        $customer = auth()->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated customer found.',
            ], 401);
        }

        $address = Address::where('customer_id', $customer->id)->where('id', $request->id)->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found.',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => $address,
        ], 200);
    }

    /**
     * Update Address By ID
     * @bodyParam name string required Name of User.
     * @bodyParam phone string required phone of User.
     * @bodyParam email string required email of User.
     * @bodyParam country string required country of User.
     * @bodyParam state string required state of User.
     * @bodyParam city string required city of User.
     * @bodyParam address string required address of User.
     * @bodyParam zip_code string required zip_code of User.
     * @bodyParam is_default number required is default address of User.
     *
     * @group Address Book
     */

    public function updateCustomerAddress(Request $request): JsonResponse
    {
        $validated = $request->validate(
            [
                'id' => 'required|integer',
                'name' => 'required|string|max:255',
                'email' => 'required|email:rfc,dns|max:255',
                'phone' => 'nullable|digits:10|regex:/^\+?[91]?\d{10}$/',
                'country' => 'required|string|max:255',
                'state' => 'required|string|max:255',
                'city' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'zip_code' => 'nullable|regex:/^\d{6}$/',
                'is_default' => 'sometimes|boolean',
                'lat' => 'required|string',
                'long' => 'required|string',
            ],
            [
                'phone.digits' => 'Please enter a valid phone number.',
                'phone.regex' => 'Please enter a valid phone number.',
                'zip_code.regex' => 'Please enter a valid zip code.',
            ]
        );

        $customer = auth()->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated customer found.',
            ], 401);
        }


        $address = Address::where('customer_id', $customer->id)->where('id', $request->id)->first();

        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found.',
            ], 404);
        }

        // If updating to default, reset other default addresses
        if ($request->has('is_default') && $request->is_default == 1) {
            Address::where('customer_id', $customer->id)->where('id', '!=', $address->id)->update(['is_default' => 0]);
        }

        $address->update($request->only([
            'name',
            'email',
            'phone',
            'country',
            'state',
            'city',
            'address',
            'zip_code',
            'is_default',
            'lat',
            'long'
        ]));

        return response()->json([
            'success' => true,
            'message' => 'Address updated successfully.',
            'data' => $address,
        ], 200);
    }

    // delete customer address 
    public function deleteCustomerAddress($address_id)
    {
        // get address by id
        $address = Address::where('id', $address_id)->first();

        // check if address exists
        if (!$address) {
            return response()->json([
                'success' => false,
                'message' => 'Address not found.',
            ], 404);
        }

        // check if the authenticated customer is the owner of this address
        if ($address->customer_id != auth()->user()->id) {
            return response()->json([
                'success' => false,
                'message' => 'You do not have permission to delete this address.',
            ], 403);
        }

        // check if this is a default address
        if ($address->is_default) {
            return response()->json([
                'success' => false,
                'message' => 'Cannot delete a default address.',
            ], 409);
        }

        // delete the address
        $address->delete();

        return response()->json([
            'success' => true,
            'message' => 'Address deleted successfully.',
        ], 200);
    }
}
