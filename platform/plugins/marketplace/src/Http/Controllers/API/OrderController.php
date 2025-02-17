<?php

namespace Botble\Marketplace\Http\Controllers\API;

use App\Http\Controllers\Controller;
use App\Services\PushNotificationService;
use Botble\Driver\Models\Driver;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Models\Notification;
use Botble\Ecommerce\Models\Order;
use Botble\Ecommerce\Models\Product;
use Botble\Marketplace\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use OneSignal;
use DB;
use Illuminate\Support\Facades\Storage;

class OrderController extends Controller
{
    // Pending orders
    public function pending()
    {
        // get order items
        $orderItems = DB::table('ec_order_product')
            ->join('vendor_orders', 'ec_order_product.order_id', '=', 'vendor_orders.order_id')
            ->where('ec_order_product.store_id', auth()->user()->store->id)
            ->where('vendor_orders.status', 'pending')
            ->where('vendor_orders.store_id', auth()->user()->store->id)
            ->select('ec_order_product.order_id', DB::raw('SUM(ec_order_product.qty) as qty'), DB::raw('SUM(ec_order_product.price) as price'), 'vendor_orders.status')
            ->groupBy('ec_order_product.order_id', 'vendor_orders.status')
            ->latest('ec_order_product.order_id')
            ->get();

        // return if no item found
        if ($orderItems->count() < 1) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found',
                'data' => $orderItems
            ], 200);
        }

        foreach ($orderItems as $item) {
            $order = Order::where('id', $item->order_id)->select('code', 'created_at')->first();
            $item->qty = (int)$item->qty;
            $item->price = $item->price;
            $item->code = $order->code;
            $item->status = $item->status;
            $item->created_at = $order->created_at;
        }

        $orders = $orderItems->map(function ($item) {
            $order = Order::where('id', $item->order_id)->first();

            $order->order_status = DB::table('vendor_orders')->where('order_id', $order->id)->where('store_id', auth()->user()->store->id)->pluck('status')->first();
            $order->qty = (int)$order->products->sum('qty');
            return [
                'id' => $order->id,
                'code' => $order->code,
                'user_id' => $order->user_id,
                'created_at' => $item->created_at,
                'user' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'avatar' => $order->user->avatar ? Storage::url($order->user->avatar) : null,
                    'dob' => $order->user->dob,
                    'phone' => $order->user->phone,
                ],
                'shipping_address' => DB::table('ec_order_addresses')->where('order_id', $order->id)->first(),
                'order_status' => $order->order_status,
                'qty' => $item->qty,
                'amount' => number_format(($item->price * $item->qty), 2),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Pending Order',
            'data' => $orders
        ], 200);
    }

    // order details
    public function orderDetails($order_id)
    {
        // get order
        $order = Order::where('id', $order_id)->select('id', 'code', 'user_id', 'created_at')->first();

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found'
            ], 404);
        }

        // get user details
        $order->user = Customer::where('id', $order->user_id)->select('id', 'name', 'email', 'avatar', 'dob', 'phone')->first();

        // add image path to user avatar
        if ($order->user->avatar) {
            $order->user->avatar = Storage::url($order->user->avatar);
        }

        // get address
        $order->address = DB::table('ec_order_addresses')->where('order_id', $order->id)->first();

        // get order products
        $order->products = DB::table('ec_order_product')->where('order_id', $order->id)->where('store_id', auth()->user()->store->id)->get();

        $order->products = $order->products->map(function ($product) {
            return [
                'id' => $product->product_id,
                'name' => $product->product_name,
                'price' => $product->price,
                'qty' => $product->qty,
                'image' => Storage::url($product->product_image),
                'is_veg' => Product::where('id', $product->product_id)->pluck('is_veg')->first(),
                'rating' => $product->rating
            ];
        });

        // Some additional details
        // $order->order_status = DB::table('vendor_orders')->where('order_id', $order->id)->where('store_id', auth()->user()->store->id)->pluck('status')->first();
        // get order status
        $o_status = DB::table('vendor_orders')->where('order_id', $order->id)->where('store_id', auth()->user()->store->id)->first();
        if ($o_status->delivered_at) {
            $order->order_status = 'delivered';
        } else {
            $order->order_status = $o_status->collected_at ? 'out for delivery' : $o_status->status;
        }

        $order->qty = (int)$order->products->sum('qty');
        $order->price = (string)number_format(($order->products->sum('price') * $order->qty), 2);

        return response()->json([
            'success' => true,
            'message' => 'Order details',
            'data' => $order
        ], 200);
    }

    // Accept Order
    public function acceptOrder($order_id, PushNotificationService $pushNotificationService)
    {

        $order = Order::find($order_id);

        if ($order) {
            $vendor_orders = DB::table('vendor_orders')->where('order_id', $order_id)->get();
            foreach ($vendor_orders as $v_order) {
                // check if order already accepted by store/vendor
                if ($v_order->store_id == auth()->user()->store->id && $v_order->status != 'pending' && $v_order->order_id == $order_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order not found in pending list'
                    ], 404);
                    break;
                }
            }

            // check if all orders are accepted
            $all_order_accepted = DB::table('vendor_orders')->where('order_id', $order_id)->where('status', 'pending')->count();

            if ($all_order_accepted > 1) {
                // update vendor specific order status
                DB::table('vendor_orders')->where('order_id', $order_id)->where('store_id', auth()->user()->store->id)->update([
                    'status' => 'accepted'
                ]);
            } else {
                // update vendor specific order status
                DB::table('vendor_orders')->where('order_id', $order_id)->where('store_id', auth()->user()->store->id)->update([
                    'status' => 'accepted'
                ]);

                // update main order status
                Order::where('id', $order_id)->update([
                    'status' => 'processing',
                    'is_confirmed' => 1
                ]);

                // save notification in database
                Notification::create([
                    'customer_id' => $order->user_id,
                    'order_id' => $order_id,
                    'prefix' => 'customer',
                    'title' => 'Order Accepted',
                    'message' => "Your order {$order->code} has been accepted and is now being processed.",
                ]);

                // Send notification to all drivers
                $driversPlayerId = Driver::whereNotNull('player_id')->pluck('player_id')->toArray();
                if ($driversPlayerId) {
                    $pushNotificationService->sendNotification(
                        "A new order {$order->code} is available. Please review and accept the order in your app.",
                        [$driversPlayerId],
                        null,
                        ['order_id' => $order->code],
                        'New Order Available'
                    );
                }

                // Send notification to customer
                $customerDeviceToken = Customer::where('id', $order->user_id)->pluck('player_id')->first();
                if ($customerDeviceToken) {
                    $pushNotificationService->sendNotification(
                        "Your order {$order->code} has been accepted and is now being prepared.",
                        [$customerDeviceToken],
                        null,
                        ['order_id' => $order->code],
                        'Order Accepted!'
                    );
                }
            }

            // create history
            DB::table('ec_order_histories')->insert([
                'action' => 'confirm_order',
                'description' => 'Order was verified by ' . auth()->user()->store->name,
                'order_id' => $order->id,
                'user_id' => auth()->user()->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order accepted successfully'
            ], 200);
        }

        return response()->json([
            'success' => false,
            'message' => 'Order not found or not belong to your store'
        ], 404);
    }

    // Reject Order
    public function rejectOrder($order_id, PushNotificationService $pushNotificationService)
    {

        $order = Order::find($order_id);

        if ($order) {
            $vendor_orders = DB::table('vendor_orders')->where('order_id', $order_id)->get();
            foreach ($vendor_orders as $v_order) {
                // check if order already accepted by store/vendor
                if ($v_order->store_id == auth()->user()->store->id && $v_order->status != 'pending' && $v_order->order_id == $order_id) {
                    return response()->json([
                        'success' => false,
                        'message' => 'Order not found in pending list'
                    ], 404);
                    break;
                }
            }

            // check if all orders are accepted
            $all_order_accepted = DB::table('vendor_orders')->where('order_id', $order_id)->where('status', 'pending')->count();

            if ($all_order_accepted > 1) {
                // update vendor specific order status
                DB::table('vendor_orders')->where('order_id', $order_id)->where('store_id', auth()->user()->store->id)->update([
                    'status' => 'canceled',
                ]);
            } else {
                // update vendor specific order status
                DB::table('vendor_orders')->where('order_id', $order_id)->where('store_id', auth()->user()->store->id)->update([
                    'status' => 'canceled',
                ]);

                // check if any of the order is accepted
                if (DB::table('vendor_orders')->where('order_id', $order_id)->where('status', 'accepted')->count() > 0) {
                    // update main order status
                    Order::where('id', $order_id)->update([
                        'status' => 'processing',
                        'is_confirmed' => 1
                    ]);

                    // Send notification to all drivers
                    $driversPlayerId = Driver::whereNotNull('player_id')->pluck('player_id')->toArray();
                    if ($driversPlayerId) {
                        $pushNotificationService->sendNotification(
                            "A new order {$order->code} is available. Please review and accept the order in your app.",
                            [$driversPlayerId],
                            null,
                            ['order_id' => $order->code],
                            'New Order Available'
                        );
                    }

                    // save notification in database
                    Notification::create([
                        'customer_id' => $order->user_id,
                        'order_id' => $order_id,
                        'prefix' => 'customer',
                        'title' => 'Order Accepted',
                        'message' => "Your order {$order->code} has been accepted and is now being processed.",
                    ]);

                    // Send notification to customer
                    $customerDeviceToken = Customer::where('id', $order->user_id)->pluck('player_id')->first();
                    if ($customerDeviceToken) {
                        $pushNotificationService->sendNotification(
                            "Your order {$order->code} has been accepted and is now being processed.",
                            [$customerDeviceToken],
                            null,
                            ['order_id' => $order->code],
                            'Order Accepted!'
                        );
                    }
                } else {
                    // update main order status
                    Order::where('id', $order_id)->update([
                        'status' => 'canceled',
                        'is_confirmed' => 1
                    ]);

                    // save notification in database
                    Notification::create([
                        'customer_id' => $order->user_id,
                        'order_id' => $order_id,
                        'prefix' => 'customer',
                        'title' => 'Order Rejected',
                        'message' => "We regret to inform you that your order {$order->code} has been rejected by store.",
                    ]);

                    // Send notification to customer
                    $customerPlayerId = Customer::where('id', $order->user_id)->pluck('player_id')->first();
                    if ($customerPlayerId) {
                        $pushNotificationService->sendNotification(
                            "We regret to inform you that your order {$order->code} has been rejected by store.",
                            [$customerPlayerId],
                            null,
                            ['order_id' => $order->code],
                            'Order Rejected!'
                        );
                    }
                }
            }

            // create history
            DB::table('ec_order_histories')->insert([
                'action' => 'cancel_order',
                'description' => 'Order was cancelled by ' . auth()->user()->store->name . ' store',
                'order_id' => $order->id,
                'user_id' => auth()->user()->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            return response()->json([
                'success' => true,
                'message' => 'Order rejected successfully'
            ]);
        }

        return response()->json([
            'success' => false,
            'message' => 'Order not found or not belong to your store'
        ]);
    }

    // Ongoing orders
    public function ongoing()
    {
        // get order items
        $orderItems = DB::table('ec_order_product')
            ->where('ec_order_product.store_id', auth()->user()->store->id)
            ->join('vendor_orders', 'vendor_orders.order_id', '=', 'ec_order_product.order_id')
            ->whereNotIn('vendor_orders.status', ['pending', 'canceled'])
            ->where('vendor_orders.store_id', auth()->user()->store->id)
            ->whereNull('vendor_orders.delivered_at')
            ->select(
                'ec_order_product.order_id',
                DB::raw('SUM(ec_order_product.qty) as qty'),
                DB::raw('SUM(ec_order_product.price) as price'),
                'vendor_orders.status'
            )
            ->groupBy('ec_order_product.order_id', 'vendor_orders.status') // Group by all non-aggregated columns
            ->orderByDesc('ec_order_product.order_id') // Use `orderByDesc` for better readability
            ->get();

        // return if no item found
        if ($orderItems->count() < 1) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found',
                'data' => $orderItems
            ], 200);
        }

        foreach ($orderItems as $item) {
            $order = Order::where('id', $item->order_id)->select('code', 'created_at')->first();
            $item->qty = (int)$item->qty;
            $item->price = $item->price;
            $item->code = $order->code;
            $item->status = $item->status;
            $item->created_at = $order->created_at;
        }

        $orders = $orderItems->map(function ($item) {
            $order = Order::where('id', $item->order_id)->first();

            $order->order_status = DB::table('vendor_orders')->where('order_id', $order->id)->where('store_id', auth()->user()->store->id)->pluck('status')->first();
            $order->order_status = DB::table('vendor_orders')->where('order_id', $order->id)->where('store_id', auth()->user()->store->id)->pluck('collected_at')->first() ? 'out for delivery' : $order->order_status;
            $order->qty = (int)$order->products->sum('qty');
            return [
                'id' => $order->id,
                'code' => $order->code,
                'user_id' => $order->user_id,
                'created_at' => $item->created_at,
                'user' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'avatar' => $order->user->avatar ? Storage::url($order->user->avatar) : null,
                    'dob' => $order->user->dob,
                    'phone' => $order->user->phone,
                ],
                'shipping_address' => DB::table('ec_order_addresses')->where('order_id', $order->id)->first(),
                'order_status' => $order->order_status,
                'qty' => $item->qty,
                'amount' => number_format(($item->price * $item->qty), 2),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Ongoing Order',
            'data' => $orders
        ], 200);
    }

    // Order history
    public function orderHistory()
    {
        // get order items
        $orderItems = DB::table('ec_order_product')
            ->where('ec_order_product.store_id', auth()->user()->store->id)
            ->join('vendor_orders', 'vendor_orders.order_id', '=', 'ec_order_product.order_id')
            ->where('vendor_orders.store_id', auth()->user()->store->id)
            ->whereNotNull('vendor_orders.delivered_at')
            ->select(
                'ec_order_product.order_id',
                DB::raw('SUM(ec_order_product.qty) as qty'),
                DB::raw('SUM(ec_order_product.price) as price'),
                'vendor_orders.status'
            )
            ->groupBy('ec_order_product.order_id', 'vendor_orders.status') // Group by all non-aggregated columns
            ->orderByDesc('ec_order_product.order_id') // Use `orderByDesc` for better readability
            ->get();

        // return if no item found
        if ($orderItems->count() < 1) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found',
                'data' => $orderItems
            ], 404);
        }

        foreach ($orderItems as $item) {
            $order = Order::where('id', $item->order_id)->select('code', 'created_at')->first();
            $item->qty = (int)$item->qty;
            $item->price = $item->price;
            $item->code = $order->code;
            $item->status = $item->status;
            $item->created_at = $order->created_at;
        }

        $orders = $orderItems->map(function ($item) {
            $order = Order::where('id', $item->order_id)->first();

            // get order status
            $o_status = DB::table('vendor_orders')->where('order_id', $order->id)->where('store_id', auth()->user()->store->id)->first();
            if ($o_status->delivered_at) {
                $order->order_status = 'delivered';
            } else {
                $order->order_status = $o_status->status;
            }

            $order->qty = (int)$order->products->sum('qty');
            return [
                'id' => $order->id,
                'code' => $order->code,
                'user_id' => $order->user_id,
                'created_at' => $order->created_at,
                'user' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'avatar' => $order->user->avatar ? Storage::url($order->user->avatar) : null,
                    'dob' => $order->user->dob,
                    'phone' => $order->user->phone,
                ],
                'shipping_address' => DB::table('ec_order_addresses')->where('order_id', $order->id)->first(),
                'order_status' => $order->order_status,
                'qty' => $item->qty,
                'amount' => number_format(($item->price * $item->qty), 2),
            ];
        });

        return response()->json([
            'success' => true,
            'message' => 'Order history',
            'data' => $orders
        ], 200);
    }

    protected function GetOrderStatus($order_id)
    {
        // get order status
        $order_status = DB::table('order_accept_status')->where('order_id', $order_id)->first();
        $status = 'pending';
        if ($order_status) {
            switch (true) {
                case !is_null($order_status->collected_at) && is_null($order_status->delivered_at):
                    $status = 'collected';
                    break;
                case !is_null($order_status->collected_at) && !is_null($order_status->delivered_at):
                    $status = 'delivered';
                    break;
                default:
                    $status = 'accepted'; // Optional: Fallback status
            }
        }
        return $status;
    }
}
