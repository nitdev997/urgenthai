<?php

namespace Botble\Ecommerce\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Razorpay\Api\Api;

class PaymentController extends Controller
{
    private $razorpay;

    public function __construct()
    {
        $this->razorpay = new Api(config('services.razorpay.key'), config('services.razorpay.secret'));
    }

    public function createOrder(Request $request)
    {
        try {
            $order = $this->razorpay->order->create([
                'amount' => $request->amount * 100, // amount in the smallest currency unit
                'currency' => 'INR',
                'payment_capture' => 1, // auto capture the payment
            ]);

            return response()->json([
                'success' => true,
                'order_id' => $order['id'],
                'razorpay_key' => config('services.razorpay.key'),
            ]);
        } catch (\Exception $e) {
            Log::error('Razorpay Order Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Something went wrong'], 500);
        }
    }

    public function verifyPayment(Request $request)
    {
        try {
            $attribures = [
                'razorpay_order_id' => $request->razorpay_order_id,
                'razorpay_payment_id' => $request->razorpay_payment_id,
                'razorpay_signature' => $request->razorpay_signature,
            ];

            $this->razorpay->utility->verifyPaymentSignature($attribures);

            return response()->json(['success' => true, 'message' => 'Payment verified successfully'], 200);
        } catch (\Exception $e) {
            Log::error('Razorpay Signature Verification Error: ' . $e->getMessage());
            return response()->json(['status' => false, 'message' => 'Payment verification failed'], 400);
        }
    }
}
