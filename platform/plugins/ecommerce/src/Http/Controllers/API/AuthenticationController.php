<?php

namespace Botble\Ecommerce\Http\Controllers\API;

use App\Services\PushNotificationService;
use Botble\Base\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Botble\Api\Http\Requests\LoginRequest;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Illuminate\Support\Facades\Auth;
use Botble\Api\Facades\ApiHelper;
use Botble\Ecommerce\Models\Customer;
use App\Services\TwilioService;
use Illuminate\Support\Facades\Log;
use Twilio\Rest\Client;
use Illuminate\Support\Facades\Validator;

class AuthenticationController extends BaseController
{
    protected $twilioService;

    public function __construct(TwilioService $twilioService)
    {
        $this->twilioService = $twilioService;
    }

    /**
     * Send OTP
     *
     * @bodyParam phone no number required The phone no of the user.
     *
     *
     * @group Authentication
     */

    public function sendOTP(Request $request, BaseHttpResponse $response, PushNotificationService $pushNotificationService)
    {

        $validator = Validator::make(
            $request->all(),
            [
                'phone' => 'required|digits:10|regex:/^\+?[91]?\d{10}$/',
            ],
            [
                'phone.required' => 'The phone number is required.',
                'phone.digits' => 'Please enter a valid phone number.',
                'phone.regex' => 'Please enter a valid phone number.'
            ]
        );

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        $phone = $request->input('phone');
        $customer = Customer::firstOrCreate(
            ['phone' => $phone],
            [
                'name' => 'Dear Customer',
                'email' => $phone . '@example.com',
                'password' => bcrypt('Welcome2024'),
            ]
        );

        $otp = rand(1000, 9999);
        // $otp = 1234;
        $customer->otp = $otp;
        $customer->otp_expires_at = now()->addMinutes(5);
        $customer->player_id = $request->player_id ?? $customer->player_id;
        $customer->save();

        $pushNotificationService->sendNotification(
            "Your OTP for authentication is {$otp}. Please enter this code to proceed. This code will expire in 5 minutes.",
            [$customer->player_id],
            null,
            ['otp' => $otp],
            'OTP Verification Code'
        );

        // Send OTP via SMS using an external service (e.g., Twilio, Nexmo)
        // Twilio::message($phone, "Your OTP is: " . $otp);
        // $message = "Your OTP is: $otp. This is a one-time password valid for 5 minutes. Do not share it with anyone.";
        // dd($this->twilioService->sendSms($phone, $message));

        return response()->json([
            'success' => true,
            'otp' => $otp,
            'message' => 'OTP sent successfully to your phone and vaild for 5 mins',
        ], 200);
    }

    /**
     * Verify OTP
     *
     * @bodyParam phone no number required The phone no of the user.
     * @bodyParam otp number required The OTP Received to User.
     *
     * @group Authentication
     */

    public function verifyOTP(Request $request, BaseHttpResponse $response)
    {
        $validator = Validator::make(
            $request->all(),
            [
                'phone' => 'required|digits:10|regex:/^\+?[91]?\d{10}$/',
            ],
            [
                'phone.required' => 'The phone number is required.',
                'phone.digits' => 'Please enter a valid phone number.',
                'phone.regex' => 'Please enter a valid phone number.'
            ]
        );

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        $phone = $request->input('phone');
        $otp = $request->input('otp');
        $redirectTo = 'home';

        $customer = Customer::where('phone', $phone)->first();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'Customer not found!',
            ], 404);
        }

        if ($customer->otp == $otp && $customer->otp_expires_at > now()) {
            if ($customer->name == 'Dear Customer') {
                $redirectTo = 'update_profile';
            }
            $token = $customer->createToken($request->input('token_name', 'Personal Access Token'));
            $customer->otp = null;
            $customer->otp_expires_at = null;
            $customer->device_type = $request->device_type ?? null;
            $customer->device_token = $request->device_token ?? null;
            $customer->player_id = $request->player_id ?? null;
            $customer->save();

            return response()->json([
                'success' => true,
                'redirectTo' => $redirectTo,
                'data' => [
                    'token' => $token->plainTextToken,
                    'user' => $customer
                ],
                'message' => 'Login successful!',
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Invalid or expired OTP!',
        ], 422);
    }

    public function login(LoginRequest $request, BaseHttpResponse $response)
    {
        if (
            Auth::guard(ApiHelper::guard())
            ->attempt([
                'email' => $request->input('email'),
                'password' => $request->input('password'),
            ])
        ) {
            $user = $request->user(ApiHelper::guard());

            $token = $user->createToken($request->input('token_name', 'Personal Access Token'));

            return $response
                ->setData(['token' => $token->plainTextToken]);
        }

        return $response
            ->setError()
            ->setCode(422)
            ->setMessage(__('Email or password is not correct!'));
    }
}
