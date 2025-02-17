<?php

namespace Botble\Marketplace\Http\Controllers\API;

use Botble\Base\Http\Controllers\BaseController;
use App\Http\Controllers\Controller;
use Botble\Ecommerce\Models\Customer;
use Botble\Marketplace\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class VendorController extends BaseController
{
    // register
    public function register(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email:rfc,dns|max:255|unique:ec_customers',
                'phone' => 'nullable|digits:10|regex:/^\+?[91]?\d{10}$/|unique:ec_customers',
                'password' => 'required|string|min:6',
                'shop_name' => 'required|string|max:255',
                'shop_phone' => 'required|digits:10|regex:/^\+?[91]?\d{10}$/|unique:mp_stores,phone',
                'country' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'shop_lat' => 'required|string',
                'shop_long' => 'required|string',
                'document' => 'required||image|mimes:jpeg,png,jpg,gif|max:2048'
            ],
            [
                'phone.digits' => 'Please enter a valid phone number.',
                'phone.regex' => 'Please enter a valid phone number.',
                'phone.unique' => 'This phone number is already registered.',
                'shop_phone.required' => 'The shop phone number is required.',
                'shop_phone.digits' => 'Please enter a valid shop phone number.',
                'shop_phone.unique' => 'This shop phone number is already registered.',
                'shop_phone.regex' => 'Please enter a valid shop phone number.',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        // create customer
        $customerId = DB::table('ec_customers')->insertGetId([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
            'password' => bcrypt($request->password),
            'is_vendor' => 1,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // create vendor info
        $infoId = DB::table('mp_vendor_info')->insertGetId([
            'customer_id' => $customerId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // create shop slug
        $shop_slug = $this->createSlug($request->shop_name);
        $slug = DB::table('slugs')->insert([
            'key' => $shop_slug,
            'reference_id' => $infoId,
            'reference_type' => 'Botble\Marketplace\Models\Store',
            'prefix' => 'stores',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // upload document
        $document = null;
        $storeName = DB::table('slugs')->where('reference_id', $infoId)->where('prefix', 'stores')->pluck('key')->first();
        if ($request->file('document')) {
            $image = $request->file('document');
            $imageName = uniqid() . '-' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path("storage/stores/{$storeName}/document/"), $imageName);
            $document = "stores/{$storeName}/document/{$imageName}";
        }

        // create store/shop
        $shop = DB::table('mp_stores')->insert([
            'name' => $request->shop_name,
            'email' => $request->email,
            'phone' => $request->shop_phone,
            'customer_id' => $customerId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'country' => $request->country,
            'address' => $request->address,
            'shop_lat' => $request->shop_lat,
            'shop_long' => $request->shop_long,
            'document' => $document
        ]);

        return response()->json(['success' => true, 'message' => 'Vendor created successfully.'], 201);
    }


    // login vendor
    public function login(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'email' => 'required|string',
            'password' => 'required|string',
            'device_type' => 'nullable|string',
            'device_token' => 'nullable|string',
            'player_id' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        // $vendor = DB::table('ec_customers')->where('email', $request->email)->first();
        $vendor = Customer::where('email', $request->email)->first();

        if (!$vendor || !Hash::check($request->password, $vendor->password)) {
            return response()->json(['success' => false, 'message' => 'Invalid credentials.'], 401);
        }

        $vendor->tokens()->delete();

        // check if account is accepted by admin
        if (!$vendor->vendor_verified_at) {
            return response()->json(['success' => false, 'message' => 'Account verification pending from adimn.'], 401);
        }

        $token = $vendor->createToken($request->input('token_name', 'Vendor Access Token'));

        // update device token
        $vendor->update([
            'device_token' => $request->device_token,
            'device_type' => $request->device_type ?? 'android',
            'player_id' => $request->player_id
        ]);

        $store = DB::table('mp_stores')->where('customer_id', $vendor->id)->first();

        if ($store->document) {
            $store->document = Storage::url($store->document);
        }

        if($vendor->avatar) {
            $vendor->avatar = Storage::url($vendor->avatar);
        }

        // store earnings
        $store->earnings = DB::table('mp_vendor_info')->where('customer_id', $vendor->id)->pluck('balance')->first();

        return response()->json([
            'success' => true,
            'message' => 'Vendor login successful!',
            'data' => [
                'token' => $token->plainTextToken,
                'vendor' => $vendor,
                'shop' => $store
            ],
        ], 200);
    }

    // Logout
    public function logout(Request $request)
    {
        $request->user()->tokens()->delete();
        return response()->json([
            'success' => true,
            'message' => 'Vendor logged out successfully.'
        ], 200);
    }

    // profile
    public function profile(Request $request)
    {
        $vendor = $request->user();

        $store = DB::table('mp_stores')->where('customer_id', $vendor->id)->first();

        if ($store->document) {
            $store->document = Storage::url($store->document);
        }

        if($vendor->avatar) {
            $vendor->avatar = Storage::url($vendor->avatar);
        }

        // store earnings
        $store->earnings = DB::table('mp_vendor_info')->where('customer_id', $vendor->id)->pluck('balance')->first();

        return response()->json([
            'success' => true,
            'message' => 'Vendor profile retrieved successfully.',
            'data' => [
                'vendor' => $vendor,
                'shop' => $store
            ],
        ], 200);
    }

    /**
     * Update Profile
     */
    public function update(Request $request)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'name' => 'required|string|max:255',
                'email' => 'required|string|email:rfc,dns|max:255|unique:ec_customers,email,' . auth()->user()->id,
                'phone' => 'nullable|digits:10|regex:/^\+?[91]?\d{10}$/|unique:ec_customers,phone,' . auth()->user()->id,
                'shop_name' => 'required|string|max:255',
                'shop_phone' => 'required|digits:10|regex:/^\+?[91]?\d{10}$/|unique:mp_stores,phone,' . auth()->user()->store->id,
                'country' => 'required|string|max:255',
                'address' => 'required|string|max:255',
                'shop_lat' => 'required|string',
                'shop_long' => 'required|string',
                'document' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
                'avatar' => 'sometimes|image|mimes:jpeg,png,jpg,gif|max:2048'
            ],
            [
                'phone.digits' => 'Please enter a valid phone number.',
                'phone.regex' => 'Please enter a valid phone number.',
                'phone.unique' => 'This phone number is already registered.',

                'shop_phone.required' => 'The shop phone number is required.',
                'shop_phone.digits' => 'Please enter a valid shop phone number.',
                'shop_phone.unique' => 'This shop phone number is already registered.',
                'shop_phone.regex' => 'Please enter a valid shop phone number.',
            ]
        );

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        // check shop phone number
        $shop = Store::where('phone', $request->shop_phone)->where('customer_id', '!=', auth()->user()->id)->first();
        if ($shop) {
            return response()->json(['success' => false, 'message' => 'Shop phone number already exists.'], 422);
        }

        // update customer
        $customer = Customer::find(auth()->user()->id);
        $customer->update([
            'name' => $request->name,
            'email' => $request->email,
            'phone' => $request->phone,
        ]);

        // update store
        $store = Store::where('customer_id', auth()->user()->id)->first();
        $store->update([
            'name' => $request->shop_name,
            'email' => $request->email,
            'phone' => $request->shop_phone,
            'country' => $request->country,
            'address' => $request->address,
            'shop_lat' => $request->shop_lat,
            'shop_long' => $request->shop_long,
        ]);

        // upload document
        if ($request->file('document')) {
            $document = $store->document;
            if ($document) {
                Storage::delete($document);
            }

            $refId = DB::table('mp_vendor_info')->where('customer_id', auth()->user()->id)->pluck('id')->first();
            $store_slug = DB::table('slugs')->where('reference_id', $refId)->where('prefix', 'stores')->pluck('key')->first();

            $image = $request->file('document');
            $imageName = uniqid() . '-' . time() . '.' . $image->getClientOriginalExtension();
            $image->move(public_path("storage/stores/{$store_slug}/document/"), $imageName);
            $store->document = "stores/{$store_slug}/document/{$imageName}";
            $store->save();
        }

        // update avatar
        if ($request->hasFile('avatar')) {
            if ($customer->avatar) {
                Storage::delete($customer->avatar);
            }

            $avatarPath = $request->file('avatar')->store('customers');

            $customer->avatar = $avatarPath;
            $customer->save();
        }

        return response()->json(['success' => true, 'message' => 'Vendor profile updated successfully.'], 200);
    }


    /**
     * Password 
     */
    public function sendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email:rfc,dns|exists:ec_customers,email',
        ]);

        //$otp = random_int(100000, 999999);

        Cache::put('vendor_otp_' . $request->email, '123456', now()->addMinutes(5));

        // Mail::raw("Your OTP for password reset is: $otp", function ($message) use ($request) {
        //     $message->to($request->email)
        //         ->subject('Password Reset Code');
        // });

        return response()->json([
            'success' => true,
            'message' => 'Code sent successfully',
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email:rfc,dns|exists:ec_customers,email',
            'code' => 'required|numeric',
        ]);

        $cachedOtp = Cache::get('vendor_otp_' . $request->email);

        if (!$cachedOtp || $cachedOtp != $request->code) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Code.',
            ], 400);
        }

        return response()->json([
            'success' => true,
            'message' => 'Code verified successfully.',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email:rfc,dns|exists:ec_customers,email',
            'password' => 'required|confirmed|min:6',
        ]);

        $vendor = Customer::where('email', $request->email)->first();
        $vendor->password = Hash::make($request->password);
        $vendor->save();

        Cache::forget('vendor_otp_' . $request->email);

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully. You can now log in.',
        ]);
    }

    // create slug
    function createSlug($shop_name)
    {
        // Generate the initial slug
        $slug = Str::slug($shop_name);

        // Check if the slug exists in the database
        $originalSlug = $slug;
        $count = 1;

        while (DB::table('slugs')->where('key', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }
}
