<?php

namespace Botble\Driver\Http\Controllers\API;

use Botble\Base\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Botble\Api\Http\Requests\LoginRequest;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Illuminate\Support\Facades\Auth;
use Botble\Api\Facades\ApiHelper;
use Botble\Driver\Models\Driver;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;

class AuthenticationController extends BaseController
{

    /**
     * Driver registration
     *
     * @bodyParam fullname string required Fullname of Driver. Example: John Smith
     * @bodyParam email string required Email of Driver. Example: johnsmith@example.com.
     * @bodyParam phone number required Phone of Driver. Example: 1234567890
     * @bodyParam password string required Password of Driver. Example: hgjGUy738
     *
     * @group Driver
     */
    public function register(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'fullname' => 'required|string|max:255',
                'email' => 'required|string|email:rfc,dns|max:255|unique:drivers',
                'phone' => ['required', 'digits:10', 'regex:/^\+?[91]?\d{10}$/',  'unique:drivers'],
                'password' => 'required|string|min:6',
            ],
            [
                'phone.required' => 'The phone number is required.',
                'phone.regex' => 'Please enter a valid phone number.',
                'phone.unique' => 'This phone number is already registered.',
                'phone.digits' => 'Please enter a valid phone number.',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        $driver = Driver::create([
            'name' => $request->fullname,
            'fullname' => $request->fullname,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' =>  Hash::make($request->password),
        ]);

        return response()->json(['success' => true, 'driver' => $driver, 'message' => 'Driver registered successfully.'], 201);
    }


    /**
     * Driver login
     *
     * @bodyParam email string required Email of Driver. Example: johnsmith@example.com
     * @bodyParam password string required Password of Driver. Example: hgjGUy738
     *
     * @group Driver
     */
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|email',
            'password' => 'required|string',
            'device_token' => 'nullable|string',
            'device_type' => 'nullable|string',
            'player_id' => 'nullable|string'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        $driver = Driver::where('email', $request->email)->first();

        if (!$driver || !Hash::check($request->password, $driver->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        if ($driver->account_status != 1) {
            return response()->json(['error' => 'Account is not activated. Contact to customer care'], 403);
        }

        $driver->tokens()->delete();

        $redirectTo = $driver->document_verification_status == 'Pending' ? 'driver_account' : 'work_orders';
        $token = $driver->createToken($request->input('token_name', 'DriverAPP Access Token'));

        // update device token
        $driver->update([
            'device_token' => $request->device_token,
            'device_type' => $request->device_type ?? 'android',
            'player_id' => $request->player_id,
        ]);

        $driver->dl_image = $driver->dl_image ? Storage::url($driver->dl_image) : null;
        $driver->number_plate_image = $driver->number_plate_image ? Storage::url($driver->number_plate_image) : null;
        $driver->avatar = $driver->avatar ? Storage::url($driver->avatar) : null;

        return response()->json([
            'success' => true,
            'redirectTo' => $redirectTo,
            'data' => [
                'token' => $token->plainTextToken,
                'driver' => $driver
            ],
            'message' => 'Login successful!',
        ], 200);
    }


    /**
     * Driver logout
     *
     * @group Driver
     */
    public function logout(Request $request)
    {

        $driver = auth()->user();

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated driver found.',
            ], 401);
        }

        $driver->currentAccessToken()->delete();

        return response()->json(['success' => true, 'message' => 'Logout successful.'], 200);
    }

    /**
     * Driver update store details
     *
     * @bodyParam avatar file image of Driver.
     * @bodyParam fullname string Fullname of Driver.
     * @bodyParam address string Address of Driver.
     * @bodyParam country string Country of Driver.
     * @bodyParam phone string Phone of Driver.
     * @bodyParam dl_number string Driving Licence Number of Driver.
     * @bodyParam number_plate_no string Number Plate Number of Driver.
     * @bodyParam dl_image file Driving Licence Image of Driver.
     * @bodyParam number_plate_image file Number Plate Image of Driver.
     *
     * @group Driver
     */

    public function storeDriverDetails(Request $request)
    {
        $driver = auth()->user();

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated driver found.',
            ], 401);
        }

        $validator = Validator::make(
            $request->all(),
            [
                'avatar' => 'nullable|image|mimes:jpeg,png,jpg|max:2048',
                'fullname' => 'required|string|max:20',
                'address' => 'required|string|max:255',
                'country' => 'required|string|max:10',
                'phone' => 'required|digits:10|regex:/^\+?[91]?\d{10}$/|unique:drivers,phone,' . auth()->user()->id,
                'dl_number' => [
                    'required',
                    'regex:/^[A-Z]{2}[0-9]{2}\s?[0-9]{4}[0-9]{7,8}$/',
                    "unique:drivers,dl_number,{$driver->id}"
                ],
                'number_plate_no' => [
                    'required',
                    'regex:/^(
                        [A-Z]{2}[0-9]{2}[A-Z]{0,2}[0-9]{3,4}|
                        [A-Z]{2}[0-9]{2}EV[0-9]{4}|
                        [A-Z]{2}[0-9]{2}TEMP[0-9]{4}
                    )$/x',
                    "unique:drivers,number_plate_no,{$driver->id}"
                ]
            ],
            [
                'phone.required' => 'The phone number is required.',
                'phone.digits' => 'Please enter a valid phone number.',
                'phone.unique' => 'This phone number is already registered.',
                'phone.regex' => 'Please enter a valid phone number.',

                'dl_number.required' => 'The driving licence number is required.',
                'dl_number.regex' => 'Please enter valid driving licence number.',
                'number_plate_no.required' => 'The number plate no is required.',
                'number_plate_no.regex' => 'Please enter valid number plate no.'
            ]
        );

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        // check phone number
        $phone = $driver->phone;
        if ($request->phone) {
            if ($driver->document_verification_status === 'Complete' && $request->phone != $driver->phone) {
                return response()->json(['success' => false, 'message' => 'You have already completed the phone number verification.'], 403);
            }
            $phone = $request->phone;
        }

        /**
         * Driving License Number
         */
        $dl_number = $driver->dl_number;
        if ($request->dl_number) {
            if ($driver->document_verification_status === 'Complete' && $request->dl_number != $driver->dl_number) {
                return response()->json(['success' => false, 'message' => 'You have already completed the dl number verification.'], 403);
            }
            $dl_number = $request->dl_number;
        }

        /**
         * Number Plate Number
         */
        $number_plate_no = $driver->number_plate_no;
        if ($request->number_plate_no) {
            if ($driver->document_verification_status === 'Complete' && $request->number_plate_no != $driver->number_plate_no) {
                return response()->json(['success' => false, 'message' => 'You have already completed the number plate number verification.'], 403);
            }
            $number_plate_no = $request->number_plate_no;
        }

        /**
         * Driving License Image
         */
        if (!$driver->dl_image) {
            $validator = Validator::make($request->all(), [
                'dl_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            ]);
        }
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        if ($request->hasFile('dl_image')) {
            if ($driver->document_verification_status === 'Complete') {
                return response()->json(['success' => false, 'message' => 'You have already completed the dl image verification.'], 403);
            }
            $dlImagePath = $request->file('dl_image')->store('driver/licence');
            $driver->dl_image = $dlImagePath;
        }

        /**
         * Number Plate Image
         */
        if (!$driver->number_plate_image) {
            $validator = Validator::make($request->all(), [
                'number_plate_image' => 'required|image|mimes:jpeg,png,jpg|max:2048',
            ]);
        }
        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }
        if ($request->hasFile('number_plate_image')) {
            if ($driver->document_verification_status === 'Complete') {
                return response()->json(['success' => false, 'message' => 'You have already completed the number plate image verification.'], 403);
            }
            $number_plateImagePath = $request->file('number_plate_image')->store('driver/number-plate');
            $driver->number_plate_image = $number_plateImagePath;
        }

        /**
         * Avatar Image
         */
        $avatar = $driver->avatar;
        if ($request->hasFile('avatar')) {
            // Delete the old image if it exists
            if ($avatar) {
                Storage::disk('public')->delete($avatar);
            }
            $avatarPath = $request->file('avatar')->store('driver/avatar');
            $avatar = $avatarPath;
        }

        /**
         * Update Details
         */

        $driver->update([
            'fullname' => $request->fullname,
            'name' => $request->fullname,
            'address' => $request->address,
            'dl_number' => $dl_number,
            'country' => $request->country,
            'number_plate' => $request->number_plate,
            'number_plate_no' => $number_plate_no,
            'avatar' => $avatar,
            'phone' => $phone
        ]);

        $driver->dl_image = $driver->dl_image ? Storage::url($driver->dl_image) : null;
        $driver->number_plate_image = $driver->number_plate_image ? Storage::url($driver->number_plate_image) : null;
        $driver->avatar = $driver->avatar ? Storage::url($driver->avatar) : null;

        return response()->json(['success' => true, 'message' => 'Driver account details stored successfully.', 'data' => $driver], 200);
    }

    /**
     * Driver details
     *
     * @header Authorization Bearer
     *
     * @response {
     *  "success": true,
     *      "driver": {
     *          "id": 1,
     *          "name": "John Smith",
     *          "email": "johnsmith@yopmail.com",
     *          "phone": "1234567890",
     *          "avatar": null,
     *          "account_status": 1,
     *          "document_verification_status": "Pending",
     *          "fullname": "John Smith",
     *          "address": null,
     *          "dl_number": null,
     *          "dl_image": null,
     *          "country": null,
     *          "number_plate_no": null,
     *          "number_plate_image": null
     *      }
     *  }
     * @group Driver
     */

    public function getDriverDetails(Request $request)
    {
        $driver = auth()->user();

        if (!$driver) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated driver found.',
            ], 401);
        }

        return response()->json([
            'success' => true,
            'driver' => [
                'id' => $driver->id,
                'name' => $driver->name,
                'email' => $driver->email,
                'phone' => $driver->phone,
                'avatar' => $driver->avatar ? Storage::url($driver->avatar) : null,
                'account_status' => $driver->account_status,
                'document_verification_status' => $driver->document_verification_status,
                'fullname' => $driver->fullname,
                'address' => $driver->address,
                'dl_number' => $driver->dl_number,
                'dl_image' => $driver->dl_image ? Storage::url($driver->dl_image) : null,
                'country' => $driver->country,
                'number_plate_no' => $driver->number_plate_no,
                'number_plate_image' => $driver->number_plate_image ? Storage::url($driver->number_plate_image) : null,
                'rating' => $driver->rating,
                'earnings' => $driver->earnings,
                'withdrawals' => $driver->withdrawals
            ],
        ], 200);
    }
}
