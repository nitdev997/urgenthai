<?php

namespace Botble\Driver\Http\Controllers\API;

use Botble\Base\Http\Controllers\BaseController;
use Illuminate\Http\Request;
use Botble\Driver\Models\Driver;
use Illuminate\Mail\Message;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class PasswordResetController extends BaseController
{

    public function sendCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:drivers,email',
        ]);

        $otp = random_int(100000, 999999);
        Driver::where('email', $request->email)->update([
            'otp' => $otp
        ]);

        // Cache::put('otp_' . $request->email, '123456', now()->addMinutes(5));

        Mail::raw("Your OTP for password reset is: $otp", function ($message) use ($request) {
            $message->to($request->email)
                ->subject('Password Reset Code')
                ->from('gurpreet.610weblab@gmail.com');
        });


        return response()->json([
            'success' => true,
            'message' => 'Code sent successfully',
        ]);
    }

    public function verifyCode(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:drivers,email',
            'code' => 'required|numeric',
        ]);

        // $cachedOtp = Cache::get('otp_' . $request->email);
        $code = Driver::where('email', $request->email)->pluck('otp')->first();

        if (!$code || $code != $request->code) {
            return response()->json([
                'success' => false,
                'message' => 'Invalid or expired Code.',
            ], 400);
        }

        Driver::where('email', $request->email)->update(['otp' => null]);

        return response()->json([
            'success' => true,
            'message' => 'Code verified successfully.',
        ]);
    }

    public function updatePassword(Request $request)
    {
        $request->validate([
            'email' => 'required|email|exists:drivers,email',
            'password' => 'required|confirmed|min:6',
        ]);

        $driver = Driver::where('email', $request->email)->first();
        $driver->password = Hash::make($request->password);
        $driver->save();

        return response()->json([
            'success' => true,
            'message' => 'Password updated successfully. You can now log in.',
        ]);
    }
}
