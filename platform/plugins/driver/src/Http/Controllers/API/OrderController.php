<?php

namespace Botble\Driver\Http\Controllers\API;

use App\Services\PushNotificationService;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Commission\Models\Commission;
use Illuminate\Http\Request;
use Botble\Driver\Models\Driver;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Models\Notification;
use Botble\Ecommerce\Models\Order;
use Botble\Ecommerce\Models\OrderProduct;
use Botble\Ecommerce\Models\Product;
use Botble\Marketplace\Models\Store;
use Carbon\Carbon;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use OneSignal;

class OrderController extends BaseController
{
    /**
     * New Orders
     *
     * @header Authorization Bearer
     *
     * @group Driver
     * @subgroup Orders
     */
    public function index()
    {
        $orders = Order::where(function ($query) {
            $query->whereDoesntHave('orderAcceptStatus', function ($query) {
                $query->where('status', 0)
                    ->where('driver_id', Auth::user()->id);
            });
        })
            ->where(function ($query) {
                $query->whereDoesntHave('orderAcceptStatus', function ($query) {
                    $query->where('status', 1);
                });
            })
            ->where('status', 'processing')
            ->select('id', 'code', 'user_id', 'created_at', 'shipping_amount')
            ->latest('id')->get();

        if ($orders->count() < 1) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found',
                'data' => $orders
            ], 200);
        }

        foreach ($orders as $order) {
            // user
            $order->user = Customer::where('id', $order->user_id)->select('id', 'name', 'email', 'avatar', 'dob', 'phone')->first();

            // products
            $storeIds = DB::table('vendor_orders')->where('order_id', $order->id)->whereNot('status', 'canceled')->pluck('store_id')->unique();
            $order->products = OrderProduct::where('order_id', $order->id)->whereIn('store_id', $storeIds)->get();

            // additional details
            $order->total_qty = $order->products->sum('qty');
            $order->stores_count = DB::table('vendor_orders')->where('order_id', $order->id)->whereNot('status', 'canceled')->count();
        }

        $result = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'code' => $order->code,
                'user_id' => $order->user->id,
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
                'status' => 'pending',
                'qty' => $order->total_qty,
                'amount' => number_format($order->products->sum(function ($product) {
                    return $product->price * $product->qty;
                }), 2),
                'delivery_fee' => $order->shipping_amount,
                'final_amount' => number_format($order->products->sum(function ($product) {
                    return $product->price * $product->qty;
                }) + $order->shipping_amount, 2),
                'stores_count' => $order->stores_count,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'New Orders',
            'data' => $result,
        ]);
    }


    /**
     * Accept Orders
     *
     * @header Authorization Bearer
     *
     * @urlParam id int required
     *
     * @group Driver
     * @subgroup Orders
     */
    // accept order
    public function accept($order_id, PushNotificationService $pushNotificationService)
    {
        $driver = auth()->user();
        $order = Order::find($order_id);
        if ($order) {

            $existingRecord = DB::table('order_accept_status')
                ->where('order_id', $order_id)
                ->where('driver_id', Auth::user()->id)
                ->first();

            if ($existingRecord) {
                DB::table('order_accept_status')->where('order_id', $order_id)
                    ->where('driver_id', Auth::user()->id)
                    ->update(['status' => 1, 'updated_at' => Carbon::now()]);

                // assign driver to order
                $order->driver_id = $driver->id;
                $order->save();
            } else {

                if (DB::table('order_accept_status')
                    ->where('order_id', $order_id)
                    ->where('status', 1)
                    ->first()
                ) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Another driver has already accepted this order',
                    ]);
                }

                DB::table('order_accept_status')->insert(
                    ['order_id' => $order_id, 'driver_id' => Auth::user()->id, 'status' => 1, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
                );

                // assign driver to order
                $order->driver_id = $driver->id;
                $order->save();
            }

            // create history
            DB::table('ec_order_histories')->insert([
                'action' => 'create_shipment',
                'description' => 'Driver ' . $driver->name . " has accepted the order for shipment",
                'order_id' => $order_id,
                'user_id' =>  $driver->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // Send push notification to vendors
            $stores = DB::table('vendor_orders')->where('order_id', $order_id)->distinct()->pluck('store_id');
            foreach ($stores as $store) {
                $store = Store::find($store);
                if ($store) {

                    // save notification in database
                    Notification::create([
                        'customer_id' => $store->customer_id,
                        'order_id' => $order_id,
                        'prefix' => 'vendor',
                        'title' => 'Order Accepted by Driver',
                        'message' => "Driver {$driver->name} has accepted order {$order->code}. Please prepare the order for delivery."
                    ]);

                    if ($store->customer->player_id) {
                        $pushNotificationService->sendNotification(
                            "Driver {$driver->name} has accepted order {$order->code}. Please prepare the order for delivery.",
                            [$store->customer->player_id],
                            null,
                            ['order_id' => $order->code],
                            'Order Accepted by Driver!'
                        );
                    }
                }
            }

            $result = [
                'status' => true,
                'message' => 'Order accepted successfully',
            ];
        } else {
            $result = [
                'status' => false,
                'message' => 'Order not found',
            ];
        }

        return response()->json($result);
    }

    // reject order
    public function reject($order_id)
    {
        $order = Order::find($order_id);
        if ($order) {
            $existingRecord = DB::table('order_accept_status')
                ->where('order_id', $order_id)
                ->where('driver_id', Auth::user()->id)
                ->first();

            if ($existingRecord) {
                DB::table('order_accept_status')->where('order_id', $order_id)
                    ->where('driver_id', Auth::user()->id)
                    ->update(['status' => 0, 'updated_at' => Carbon::now()]);
            } else {

                if (DB::table('order_accept_status')
                    ->where('order_id', $order_id)
                    ->where('status', 1)
                    ->first()
                ) {
                    return response()->json([
                        'status' => false,
                        'message' => 'Order not available'
                    ]);
                }

                DB::table('order_accept_status')->insert(
                    ['order_id' => $order_id, 'driver_id' => Auth::user()->id, 'status' => 0, 'created_at' => Carbon::now(), 'updated_at' => Carbon::now()],
                );
            }

            $result = [
                'status' => true,
                'message' => 'Order rejected successfully',
            ];
        } else {
            $result = [
                'status' => false,
                'message' => 'Order not found',
            ];
        }

        return response()->json($result);
    }

    // my orders
    public function myOrders()
    {
        $orders = DB::table('ec_orders')
            ->join('vendor_orders', 'vendor_orders.order_id', '=', 'ec_orders.id')
            ->join('order_accept_status', 'order_accept_status.order_id', '=', 'ec_orders.id')
            ->where('vendor_orders.status', '!=', 'canceled')
            ->where('order_accept_status.status', '=', 1)
            ->where('order_accept_status.driver_id', '=', auth()->user()->id)
            ->where('order_accept_status.delivered_at', '=', null)
            ->select(
                'ec_orders.id',
                'ec_orders.code',
                'ec_orders.user_id',
                'ec_orders.created_at',
                'ec_orders.shipping_amount',
            )
            ->latest()
            ->distinct()
            ->get();

        if ($orders->count() < 1) {
            return response()->json([
                'status' => false,
                'message' => 'No orders found',
                'data' => $orders
            ], 200);
        }

        $orders->each(function ($order) {

            // user
            $order->user = Customer::where('id', $order->user_id)->select('id', 'name', 'email', 'avatar', 'dob', 'phone')->first();

            $storeIds = DB::table('vendor_orders')->where('order_id', $order->id)->whereNot('status', 'canceled')->pluck('store_id')->unique();
            $order->products = OrderProduct::where('order_id', $order->id)->whereIn('store_id', $storeIds)->get();

            $order->total_qty = $order->products->sum('qty');
            $order->stores_count = DB::table('vendor_orders')->where('order_id', $order->id)->whereNot('status', 'canceled')->count();

            // get order status
            $order_status = DB::table('order_accept_status')->where('order_id', $order->id)->where('driver_id', auth()->user()->id)->first();
            $order->order_status = 'pending';
            if ($order_status) {
                switch (true) {
                    case !is_null($order_status->collected_at) && is_null($order_status->delivered_at):
                        $order->order_status = 'collected';
                        break;
                    case !is_null($order_status->collected_at) && !is_null($order_status->delivered_at):
                        $order->order_status = 'delivered';
                        break;
                    default:
                        $order->order_status = 'accepted'; // Optional: Fallback status
                }
            }
        });

        $result = $orders->map(function ($order) {
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
                'qty' => $order->total_qty,
                'amount' => number_format($order->products->sum(function ($product) {
                    return $product->price * $product->qty;
                }), 2),
                'delivery_fee' => $order->shipping_amount,
                'stores_count' => $order->stores_count,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'My Orders',
            'data' => $result
        ]);
    }

    // order collect
    public function collect(Request $request, $order_id, PushNotificationService $pushNotificationService)
    {
        $validate = Validator::make($request->all(), [
            'store_id' => 'required|integer'
        ]);

        if ($validate->fails()) {
            return response()->json([
                'status' => false,
                'message' => $validate->errors()
            ], 422);
        }

        if (OrderProduct::where('order_id', $order_id)->where('store_id', $request->store_id)->count() < 1) {
            return response()->json([
                'status' => false,
                'message' => 'Invalid store id',
            ], 422);
        }

        $order = DB::table('order_accept_status')->where('order_id', $order_id)->where('status', 1)->where('driver_id', Auth::user()->id)->first();

        if ($order) {
            // check all orders are collected
            $all_order_collected = DB::table('vendor_orders')->where('order_id', $order_id)->where('status', 'accepted')->whereNull('collected_at')->count();

            if ($all_order_collected > 1) {
                // update vendor specific collected at status
                DB::table('vendor_orders')->where('order_id', $order_id)->where('store_id', $request->store_id)->update([
                    'collected_at' => now()
                ]);
            } else {
                // update vendor specific collected at status
                DB::table('vendor_orders')->where('order_id', $order_id)->where('store_id', $request->store_id)->update([
                    'collected_at' => now()
                ]);

                // update main order collected at status
                DB::table('order_accept_status')->where('order_id', $order_id)->where('status', 1)->where('driver_id', auth()->user()->id)->update([
                    'collected_at' => now()
                ]);

                // update shipment status
                DB::table('ec_shipments')->where('order_id', $order_id)->update(['status' => 'picked']);
            }

            // create history
            DB::table('ec_order_histories')->insert([
                'action' => 'update_shipping_status',
                'description' => 'Driver ' . auth()->user()->name . ' has collected the order from ' . Store::where('id', $request->store_id)->pluck('name')->first() . ' store',
                'order_id' => $order_id,
                'user_id' => auth()->user()->id,
                'created_at' => Carbon::now(),
                'updated_at' => Carbon::now()
            ]);

            // send push notification to customer
            $getOrder = Order::where('id', $order_id)->select('user_id', 'code')->first();

            // save notification in database
            Notification::create([
                'customer_id' => $getOrder->user_id,
                'order_id' => $order_id,
                'prefix' => 'customer',
                'title' => 'Order Collected by Driver',
                'message' => 'Driver ' . auth()->user()->name . ' has collected order ' . $getOrder->code . ' from ' . Store::where('id', $request->store_id)->pluck('name')->first() . ' store'
            ]);

            $customerPlayerId = Customer::where('id', $getOrder->user_id)->pluck('player_id')->first();
            if ($customerPlayerId) {
                $pushNotificationService->sendNotification(
                    'Driver ' . auth()->user()->name . ' has collected order ' . $getOrder->code . ' from ' . Store::where('id', $request->store_id)->pluck('name')->first() . ' store',
                    [$customerPlayerId],
                    null,
                    ['order_id' => $getOrder->code],
                    'Order Collected by Driver!'
                );
            }

            $result = [
                'status' => true,
                'message' => 'Order collected successfully',
            ];
        } else {
            $result = [
                'status' => false,
                'message' => 'Order not found',
            ];
        }

        return response()->json($result);
    }

    // order deliver
    public function deliver($order_id, PushNotificationService $pushNotificationService)
    {

        $order = DB::table('order_accept_status')->where('order_id', $order_id)->where('status', 1)->where('driver_id', Auth::user()->id)->first();

        if ($order) {
            $getOrder = Order::where('id', $order_id)->first();

            if ($order->collected_at == null) {
                $result = [
                    'status' => false,
                    'message' => 'Order not collected yet',
                ];
            } elseif ($order->delivered_at != null) {
                $result = [
                    'status' => false,
                    'message' => 'Order already delivered',
                ];
            } else {
                // update delivery time
                DB::table('order_accept_status')->where('order_id', $order_id)->where('status', 1)->where('driver_id', Auth::user()->id)->update([
                    'delivered_at' => Carbon::now()
                ]);

                // update vendor specific delivered at status
                DB::table('vendor_orders')->where('order_id', $order_id)->where('status', 'accepted')->update([
                    'delivered_at' => now()
                ]);

                // confirm payment
                DB::table('payments')->where('order_id', $order_id)->update(['status' => 'completed', 'updated_at' => Carbon::now()]);

                // update shipment status
                DB::table('ec_shipments')->where('order_id', $order_id)->update(['status' => 'delivered', 'cod_status' => 'completed']);

                // update status in order table
                Order::where('id', $order_id)->update([
                    'status' => 'completed',
                    'completed_at' => Carbon::now()
                ]);

                $order_price = Order::where('id', $order_id)->select('user_id', 'code', 'amount', 'shipping_amount')->first();
                // create history
                $history = [
                    [
                        'action' => 'confirm_payment',
                        'description' => 'Order payment was confirmed (amount ' . $order_price->amount + $order_price->shipping_amount . ')',
                    ],
                    [
                        'action' => 'update_shipping_status',
                        'description' => 'Order delivered by ' . Auth::user()->name,
                    ],
                    [
                        'action' => 'mark_order_as_completed',
                        'description' => 'Order is marked as completed at ' . Carbon::now(),
                    ]
                ];

                foreach ($history as $item) {
                    DB::table('ec_order_histories')->insert([
                        'action' => $item['action'],
                        'description' => $item['description'],
                        'order_id' => $order_id,
                        'user_id' => auth()->user()->id,
                        'created_at' => Carbon::now(),
                        'updated_at' => Carbon::now()
                    ]);
                }

                // Send push notification to vendors
                $stores = DB::table('vendor_orders')->where('order_id', $order_id)->where('status', 'accepted')->distinct()->pluck('store_id');
                foreach ($stores as $store) {
                    $store = Store::find($store);
                    if ($store) {
                        // save notification in database
                        Notification::create([
                            'customer_id' => $store->customer->id,
                            'order_id' => $order_id,
                            'prefix' => 'vendor',
                            'title' => 'Order Delivered',
                            'message' => 'Great news! Your order ' . $order_price->code . ' has been successfully delivered to the customer.',
                        ]);

                        $vendorBalance = DB::table('mp_vendor_info')->where('customer_id', $store->customer->id)->pluck('balance')->first();
                        $orderAmountForStore = OrderProduct::where('order_id', $order_id)->where('store_id', $store->id)->sum('price');

                        // add earnings to vendor account
                        $vendorCommission = Commission::pluck('vendor')->first();
                        if( $vendorCommission < 1) {
                            $vendorEarnings = $orderAmountForStore;
                        } elseif ($vendorCommission > 90) {
                            $vendorCommission = 0 . (100 - $vendorCommission);
                            $vendorEarnings = $orderAmountForStore * (0 . '.' . $vendorCommission);
                        } else {
                            $vendorCommission = 100 - $vendorCommission;
                            $vendorEarnings = $orderAmountForStore * (0 . '.' . $vendorCommission);
                        }

                        DB::table('mp_vendor_info')->where('customer_id', $store->customer->id)->update([
                            'balance' => $vendorBalance + $vendorEarnings
                        ]);

                        if ($store->customer->player_id) {
                            $pushNotificationService->sendNotification(
                                'Great news! Your order ' . $order_price->code . ' has been successfully delivered to the customer.',
                                [$store->customer->player_id],
                                null,
                                ['order_id' => $order_price->code],
                                'Order Delivered Successfully!'
                            );
                        }
                    }
                }

                // save notification in database
                Notification::create([
                    'customer_id' => $order_price->user_id,
                    'order_id' => $order_id,
                    'prefix' => 'customer',
                    'title' => 'Order Delivered',
                    'message' => "Your order {$order_price->code} has been delivered successfully.",
                ]);

                // Send notification to customer
                $customerDeviceToken = Customer::where('id', $order_price->user_id)->pluck('player_id')->first();
                if ($customerDeviceToken) {
                    $pushNotificationService->sendNotification(
                        "Your order {$order_price->code} has been delivered successfully.",
                        [$customerDeviceToken],
                        null,
                        ['order_id' => $order_price->code],
                        'Order Delivered!'
                    );
                }

                $driver = auth()->user();

                // add earnings to drivers account
                $driverOrdersCount = Order::where('driver_id', $driver->id)->where('status', 'completed')->count();
                if ($driverOrdersCount > 10) {
                    $earnings = $getOrder->shipping_amount;
                } else {
                    $driverCommission = Commission::pluck('driver')->first();
                    if ($driverCommission < 1) {
                        $earnings = $getOrder->shipping_amount;
                    } elseif ($driverCommission > 90) {
                        $driverCommission = 0 . (100 - $driverCommission);
                        $earnings = $getOrder->shipping_amount * (0 . '.' . $driverCommission);
                    }else {
                        $driverCommission = 100 - $driverCommission;
                        $earnings = $getOrder->shipping_amount * (0 . '.' . $driverCommission);
                    }
                }

                $driver->earnings = $driver->earnings + $earnings;
                $driver->save();

                $result = [
                    'status' => true,
                    'message' => 'Order delivered successfully',
                ];
            }
        } else {
            $result = [
                'status' => false,
                'message' => 'Order not found',
            ];
        }

        return response()->json($result);
    }

    // order history
    public function orderHistory()
    {
        $orders = DB::table('ec_orders')
            ->join('vendor_orders', 'vendor_orders.order_id', '=', 'ec_orders.id')
            ->join('order_accept_status', 'order_accept_status.order_id', '=', 'ec_orders.id')
            ->where('vendor_orders.status', '!=', 'canceled')
            ->where('order_accept_status.status', '=', 1)
            ->where('order_accept_status.driver_id', '=', auth()->user()->id)
            ->where('order_accept_status.delivered_at', '!=', null)
            ->select(
                'ec_orders.id',
                'ec_orders.code',
                'ec_orders.user_id',
                'ec_orders.created_at',
                'ec_orders.shipping_amount',
            )
            ->distinct()
            ->latest('id')
            ->get();

        if ($orders->count() < 1) {
            return response()->json([
                'status' => false,
                'message' => 'No orders found',
                'data' => $orders
            ], 404);
        }

        $orders->each(function ($order) {
            // user
            $order->user = Customer::where('id', $order->user_id)->select('id', 'name', 'email', 'avatar', 'dob', 'phone')->first();

            $storeIds = DB::table('vendor_orders')->where('order_id', $order->id)->whereNot('status', 'canceled')->pluck('store_id')->unique();
            $order->products = OrderProduct::where('order_id', $order->id)->whereIn('store_id', $storeIds)->get();

            $order->total_qty = $order->products->sum('qty');
            $order->stores_count = $order->products->pluck('store_id')->unique()->count();

            // get order status
            $order_status = DB::table('order_accept_status')->where('order_id', $order->id)->where('driver_id', auth()->user()->id)->first();
            $order->order_status = 'pending';
            if ($order_status) {
                switch (true) {
                    case !is_null($order_status->collected_at) && is_null($order_status->delivered_at):
                        $order->order_status = 'collected';
                        break;
                    case !is_null($order_status->collected_at) && !is_null($order_status->delivered_at):
                        $order->order_status = 'delivered';
                        break;
                    default:
                        $order->order_status = 'accepted'; // Optional: Fallback status
                }
            }
        });

        $result = $orders->map(function ($order) {
            return [
                'id' => $order->id,
                'code' => $order->code,
                'user_id' => $order->user->id,
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
                'qty' => $order->total_qty,
                'amount' => number_format($order->products->sum(function ($product) {
                    return $product->price * $product->qty;
                }), 2),
                'delivery_fee' => $order->shipping_amount,
                'final_amount' => number_format($order->products->sum(function ($product) {
                    return $product->price * $product->qty;
                }) + $order->shipping_amount, 2),
                'stores_count' => $order->stores_count,
            ];
        });

        return response()->json([
            'status' => true,
            'message' => 'Order History',
            'data' => $result
        ]);
    }

    // order details
    public function orderDetails($order_id)
    {
        $order = Order::with(['user:id,name,avatar,dob,email,phone'])
            ->where('id', $order_id)
            ->latest('id')->first();

        if (!$order) {
            return response()->json([
                'status' => false,
                'message' => 'Order not found',
            ], 404);
        }

        // get order status
        $order_status = DB::table('order_accept_status')->where('order_id', $order_id)->where('driver_id', Auth::user()->id)->first();
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

        // get stores address
        $storeIds = DB::table('vendor_orders')->where('order_id', $order_id)->whereNot('status', 'canceled')->pluck('store_id')->unique();
        $stores = Store::select('id', 'name', 'email', 'phone', 'address', 'country', 'state', 'city', 'zip_code', 'shop_lat', 'shop_long')->whereIn('id', $storeIds)->get();
        $stores->each(function ($store) use ($order_id) {
            $store->order_status = DB::table('vendor_orders')->where('order_id', $order_id)->where('store_id', $store->id)->pluck('status')->first();
        });

        $order->products = OrderProduct::where('order_id', $order_id)->whereIn('store_id', $storeIds)->get();

        return response()->json([
            'stats' => true,
            'message' => 'Order details',
            'data' => [
                'id' => $order->id,
                'code' => $order->code,
                'user_id' => $order->user->id,
                'created_at' => $order->created_at,
                'user' => [
                    'id' => $order->user->id,
                    'name' => $order->user->name,
                    'email' => $order->user->email,
                    'dob' => $order->user->dob,
                    'avatar' => $order->user->avatar ? Storage::url($order->user->avatar) : null,
                    'phone' => $order->user->phone
                ],
                'shipping_address' => DB::table('ec_order_addresses')->where('order_id', $order->id)->first(),
                'stores' => $stores,
                'products' => $order->products->map(function ($product) use ($order_id) {
                    $storeId = OrderProduct::where('product_id', $product->product_id)->where('order_id', $order_id)->pluck('store_id')->first();
                    $store = Store::select('name', 'shop_lat', 'shop_long')->where('id', $storeId)->first();
                    return [
                        'id' => $product->product_id,
                        'name' => $product->product_name,
                        'price' => $product->price,
                        'qty' => $product->qty,
                        'image' => Storage::url($product->product_image),
                        'store_id' => $storeId,
                        'store_name' => $store->name,
                        'shop_lat' => $store->shop_lat,
                        'shop_long' => $store->shop_long,
                        'order_status' => DB::table('vendor_orders')->where('order_id', $order_id)->where('store_id', $storeId)->pluck('status')->first() ?? 'pending',
                        'is_veg' => Product::where('id', $product->product_id)->pluck('is_veg')->first()
                    ];
                }),
                'order_status' => $status,
                'qty' => $order->products->sum('qty'),
                // 'amount' => $order->amount,
                'amount' => number_format($order->products->sum(function ($product) {
                    return $product->price * $product->qty;
                }), 2),
                'delivery_fee' => $order->shipping_amount,
                'final_amount' => number_format($order->products->sum(function ($product) {
                    return $product->price * $product->qty;
                }) + $order->shipping_amount, 2),
                'stores_count' => $storeIds->count(),
            ]
        ]);
    }
}
