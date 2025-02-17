<?php

namespace Botble\Ecommerce\Http\Controllers\API;

use Botble\Base\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Botble\Api\Http\Requests\LoginRequest;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Illuminate\Support\Facades\Auth;
use Botble\Api\Facades\ApiHelper;
use Botble\Ecommerce\Models\Customer;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Storage;
use Botble\Ecommerce\Models\Address;
use Botble\Ecommerce\Http\Resources\CustomerAddressResource;

class UserController extends BaseController
{

    /**
     * Get Profile
     *
     * @bodyParam Bearer Token Required
     *
     *
     * @group User Profile
     */

    public function getProfile(): JsonResponse
    {
        $customer = auth()->user();

        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'No authenticated customer found.'], 401);
        }
        $data = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'avatar' => $customer->avatar ? Storage::url($customer->avatar) : null,
            'is_new_user' => $customer->is_new_user
        ];
        return response()->json([
            'success' => true,
            'data'    => $data,
        ], 200);
    }

    /**
     * Update Profile
     *
     * @bodyParam id number required User id of User.
     * @bodyParam name string required Name of User.
     * @bodyParam phone string required phone of User.
     * @bodyParam email string required email of User.
     *
     * @group User Profile
     */

    public function updateProfile(Request $request): JsonResponse
    {
        $customer = auth()->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated customer found.',
            ], 401);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'name'  => 'required|string|max:255',
                'email' => 'required|email|unique:ec_customers,email,' . $customer->id,
                'phone' => 'required|digits_between:5,15|unique:ec_customers,phone,' . $customer->id,
                'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
            ],
            [
                'phone.digits_between' => 'Please enter a valid phone number.',
                'phone.unique' => 'This phone number is already registered.',
            ]
        );

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed.',
                'errors'  => $validator->errors(),
            ], 422);
        }

        $customer->update($request->only('name', 'email', 'phone'));
        $customer->update(['is_new_user' => false]);

        if ($request->hasFile('avatar')) {
            if ($customer->avatar) {
                Storage::delete($customer->avatar);
            }

            $avatarPath = $request->file('avatar')->store('customers');

            $customer->avatar = $avatarPath;
            $customer->save();
        }

        $data = [
            'id' => $customer->id,
            'name' => $customer->name,
            'email' => $customer->email,
            'phone' => $customer->phone,
            'avatar' => $customer->avatar ? Storage::url($customer->avatar) : null
        ];
        return response()->json([
            'success' => true,
            'message' => 'Profile updated successfully.',
            'data'    => $data,
        ], 200);
    }

    /**
     * Get User Address
     *
     * @bodyParam id number required User id of User.
     * @bodyParam name string required Name of User.
     * @bodyParam phone string required phone of User.
     * @bodyParam email string required email of User.
     *
     * @group User Profile
     */
    public function getCustomerAllAddresses(): JsonResponse
    {
        $customer = auth()->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated customer found.',
            ], 401);
        }

        // Fetch the addresses using the customer's ID
        $addresses = Address::where('customer_id', $customer->id)->latest('id')->get();

        // If no addresses found
        if ($addresses->isEmpty()) {
            return response()->json([
                'success' => false,
                'message' => 'No addresses found for the customer.',
                'data' => $addresses
            ], 200);
        }

        return response()->json([
            'success' => true,
            'message' => 'addresses',
            'data' => CustomerAddressResource::collection($addresses),
        ], 200);
    }
}
