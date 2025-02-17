<?php

namespace Botble\Ecommerce\Http\Controllers\API;

use App\Models\OrderHistoryStatus;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Http\Controllers\Fronts\PublicCartController;
use Illuminate\Http\Request;
use Botble\Ecommerce\Http\Requests\CartRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Botble\Ecommerce\Facades\Cart;
use Botble\Ecommerce\Models\Product;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Str;
use Botble\Ecommerce\Models\Address;
use Botble\Payment\Models\Payment;
use Botble\Payment\Enums\PaymentStatusEnum;
use Botble\Ecommerce\Models\Customer;
use Botble\Ecommerce\Models\Order;
use Botble\Marketplace\Models\Store;
use OneSignal;
use App\Services\PushNotificationService;
use Botble\Ecommerce\Models\Notification;
use Botble\Ecommerce\Models\OrderProduct;
use Illuminate\Support\Facades\DB;

class OrderPlaceController extends PublicCartController
{
    public function checkout(Request $request, PushNotificationService $pushNotificationService)
    {
        $validated = $request->validate([
            'payment_method' => 'required|string',
            'payment_id' => 'nullable|string',
            'address_id' => 'required|integer|exists:ec_customer_addresses,id',
            'distance' => 'required|string',
            'delivery_fee' => 'required|string'
        ]);

        $customer = auth()->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated customer found.',
            ], 401);
        }

        /**
         * First Check Item inside Cart ,it exist or not
         */
        $cartItem = \DB::table('temporary_carts')
            ->where('customer_id', $customer->id)
            ->get();

        if ($cartItem->isEmpty()) {
            return response()->json([
                'message' => 'Your cart is empty.'
            ], 404);
        }

        /**
         * Second Check Item inside Cart Under Stock Or Not
         */
        $outOfStockItems = [];

        foreach ($cartItem as $item) {
            $product = Product::find($item->product_id);

            if ($product->quantity) {
                // check product quantity
                if ($item->quantity > $product->quantity) {
                    return response()->json([
                        'success' => false,
                        'message' => "Sorry, we only have {$product->quantity} unit(s) of {$product->name} in stock. Please adjust your quantity.",
                    ], 400);
                }

                // update product quantity
                // $product->quantity = $product->quantity - $item->quantity;
            }


            if ($product && $product->isOutOfStock()) {
                $outOfStockItems[] = $product->id;
            }

            // if ($product->quantity && $product->quantity === 0) {
            //     $product->stock_status = 'out_of_stock';
            // }
            // $product->save();
        }
        if (!empty($outOfStockItems)) {
            return response()->json([
                'message' => 'Some items in your cart are out of stock.',
                'out_of_stock_items' => $outOfStockItems
            ], 400);
        }

        /**
         * Third Check Payment Method COD Or Other
         */
        try {
            DB::beginTransaction();
            // if ($validated['payment_method'] == 'cod') {
                $orderToken = Str::random(25);
                $order = DB::table('ec_orders')->insertGetId([
                    'shipping_method' => 'default',
                    'amount' => DB::table('temporary_carts')->where('customer_id', $customer->id)->sum('price'),
                    'user_id' => $customer->id,
                    'sub_total' => DB::table('temporary_carts')->where('customer_id', $customer->id)->sum('price'),
                    'status' => 'pending',
                    'token' => $orderToken,
                    'is_finished' => 1,
                    'shipping_amount' => $request->delivery_fee,
                    'distance_in_km' => $request->distance,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                if($validated['payment_method'] == 'cod') {
                    $paymentStatus = PaymentStatusEnum::PENDING;
                    $chargeId = Str::upper(Str::random(10));
                }else {
                    $paymentStatus = PaymentStatusEnum::COMPLETED;
                    $chargeId = $validated['payment_id'];
                }
                $payment = Payment::query()->create([
                    'amount' => DB::table('temporary_carts')->where('customer_id', $customer->id)->sum('price'),
                    'currency' => 'INR',
                    'payment_channel' => $validated['payment_method'],
                    'status' => $paymentStatus,
                    'payment_type' => 'confirm',
                    'order_id' => $order,
                    'charge_id' => $chargeId,
                    'user_id' => 0,
                    'customer_id' => $customer->id,
                    'customer_type' => Customer::class,
                ]);

                DB::table('ec_orders')->where('id', $order)->update([
                    'payment_id' => $payment->id,
                    'code' => '#202400' . $order
                ]);

                DB::table('ec_order_histories')->insert([
                    'order_id' => $order,
                    'user_id' => $customer->id,
                    'action' => 'create_order_from_payment_page',
                    'description' => 'Order was created from checkout page',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($cartItem as $item) {
                    $product = Product::query()->find($item->product_id);
                    DB::table('ec_order_product')->insert([
                        'order_id' => $order,
                        'qty' => $item->quantity,
                        'price' => ($product->sale_price && $product->sale_price > 0) ? $product->sale_price : $product->price,
                        'product_id' => $item->product_id,
                        'product_name' => $product->name,
                        'product_image' => $product->original_product->image ?? null,
                        'weight' => $product->weight,
                        'tax_amount' => 0,
                        'product_type' => $product->product_type,
                        'store_id' => $product->store_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }
                $address = Address::where('id', $validated['address_id'])->first();
                DB::table('ec_order_addresses')->insert([
                    'order_id' => $order,
                    'name' => $address->name,
                    'phone' => $address->phone,
                    'email' => $address->email,
                    'state' => $address->state,
                    'country' => $address->country,
                    'city' => $address->city,
                    'address' => $address->address,
                    'zip_code' => $address->zip_code,
                    'type' => 'shipping_address',
                    'lat' => $address->lat,
                    'long' => $address->long,
                ]);

                $stores = DB::table('temporary_carts')->where('customer_id', $customer->id)->distinct()->pluck('store_id');
                // Send push notification to all stores
                foreach ($stores as $store) {
                    // save suborders for stores
                    DB::table('vendor_orders')->insert([
                        'store_id' => $store,
                        'order_id' => $order,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $store = Store::find($store);
                    if ($store) {
                        $orderCode = Order::where('id', $order)->pluck('code')->first();

                        // save notification in database
                        Notification::create([
                            'customer_id' => $store->customer->id,
                            'order_id' => $order,
                            'prefix' => 'vendor',
                            'title' => 'New Order',
                            'message' => "You have received a new order {$orderCode}",
                        ]);

                        if ($store->customer->player_id) {
                            $pushNotificationService->sendNotification(
                                "You have received a new order {$orderCode}",
                                [$store->customer->player_id],
                                null,
                                ['order_id' => $orderCode],
                                'New Order'
                            );
                        }
                    }
                }

                DB::table('temporary_carts')
                    ->where('customer_id', $customer->id)
                    ->delete();
            // } else {
            //     //
            // }

            // update stock
            foreach ($cartItem as $item) {
                $product = Product::find($item->product_id);
                if($product->quantity) {
                    $product->quantity = $product->quantity - $item->quantity;

                    if($product->quantity == 0) {
                        $product->stock_status = 'out_of_stock';
                    }
                    $product->save();
                }
            }

            DB::commit();

            return response()->json(['success' => true, 'message' => 'Order successfully placed.'], 200);
        } catch (\Exception $e) {
            DB::rollback();

            return response()->json(['error' => 'Order could not be placed. Please try again.', 'msg' => $e], 500);
        }
    }

    /**
     * My Orders
     */
    public function myOrders(Request $request)
    {
        $searchQuery = $request->input('search');

        $orderIds = auth()->user()->orders()
            ->whereHas('products', function ($query) use ($searchQuery) {
                if ($searchQuery) {
                    $query->where('product_name', 'like', "%{$searchQuery}%");
                }
            })
            ->pluck('id');


        $orders = auth()->user()->orders()
            ->whereIn('id', $orderIds)
            ->when(request('filter') == 'favorite', function ($query) {
                $query->where('is_favorite', true);
            })
            ->select('id', 'code', 'created_at', 'amount', 'shipping_amount', 'status', 'rating', 'is_favorite', 'driver_id', 'driver_rating')
            ->latest('id')
            ->paginate(20);

        if ($orders->count() == 0) {
            return response()->json([
                'success' => false,
                'message' => 'No orders found.',
                'data' => $orders->items()
            ], 200);
        };

        $result = $orders->map(function ($order) {
            // get order status
            if ($order->status == 'canceled') {
                $status = 'canceled';
            } else {
                $order_status = DB::table('order_accept_status')->where('order_id', $order->id)->first();
                $status = $order->status == 'processing' ? 'preparing' : 'pending';
                if ($order_status) {
                    switch (true) {
                        case !is_null($order_status->collected_at) && is_null($order_status->delivered_at):
                            $status = 'out for delivery';
                            break;
                        case !is_null($order_status->collected_at) && !is_null($order_status->delivered_at):
                            $status = 'delivered';
                            break;
                        default:
                            $status = 'preparing';
                    }
                }
            }

            return [
                'id' => $order->id,
                'code' => $order->code,
                'created_at' => $order->created_at,
                'status' => $status,
                'qty' => $order->products->sum('qty'),
                'amount' => (string)number_format($order->amount + $order->shipping_amount, 2),
                'rating' => $order->rating,
                'is_favorite' => $order->is_favorite,
                'driver_id' => $order->driver_id,
                'driver_rating' => $order->driver_rating,
                'products' => $order->products->map(function ($product) {
                    return [
                        'id' => $product->id,
                        'name' => $product->product_name,
                        'image' => $product->product_image ? Storage::url($product->product_image) : null,
                        'qty' => $product->qty,
                        'price' => (string)number_format($product->price, 2),
                        'store_id' => $product->store_id,
                        'rating' => $product->rating,
                        'is_veg' => Product::where('id', $product->product_id)->pluck('is_veg')->first()
                    ];
                }),
            ];
        });


        return response()->json([
            'success' => true,
            'message' => 'My Orders',
            'data' => $result
        ]);
    }

    /**
     * Order Details
     */
    public function orderDetails($orderId)
    {
        $order = auth()->user()->orders()->find($orderId);

        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        // products
        $storeIds = DB::table('vendor_orders')->where('order_id', $order->id)->whereNot('status', 'canceled')->pluck('store_id')->unique();
        $acceptedProducts = OrderProduct::where('order_id', $order->id)->whereIn('store_id', $storeIds)->get();

        // additional details
        $order->total_qty = $acceptedProducts->sum('qty');
        $order->stores_count = DB::table('vendor_orders')->where('order_id', $order->id)->whereNot('status', 'canceled')->count();

        $product = $order->products->map(function ($product) use ($orderId) {
            $store = Store::select('id', 'name', 'shop_lat', 'shop_long')->where('id', OrderProduct::where('product_id', $product->product_id)->whereNotNull('store_id')->pluck('store_id')->first())->first();

            return [
                'id' => $product->id,
                'name' => $product->product_name,
                'price' => $product->price,
                'quantity' => $product->qty,
                'image' => Storage::url($product->product_image) ?? null,
                'store_id' => $store->id ?? null,
                'store_name' => $store->name ?? null,
                'shop_lat' => $store->shop_lat ?? null,
                'shop_long' => $store->shop_long ?? null,
                'order_status' => DB::table('vendor_orders')->where('order_id', $orderId)->where('store_id', $store->id)->pluck('status')->first() ?? 'pending',
                'rating' => $product->rating,
                'is_veg' => Product::where('id', $product->product_id)->pluck('is_veg')->first(),
            ];
        });

        if ($order->status == 'canceled') {
            $status = 'canceled';
        } else {
            $order_status = DB::table('order_accept_status')->where('order_id', $order->id)->first();
            $status = $order->status == 'processing' ? 'preparing' : 'pending';
            if ($order_status) {
                switch (true) {
                    case !is_null($order_status->collected_at) && is_null($order_status->delivered_at):
                        $status = 'out for delivery';
                        break;
                    case !is_null($order_status->collected_at) && !is_null($order_status->delivered_at):
                        $status = 'delivered';
                        break;
                    default:
                        $status = 'preparing';
                }
            }
        }

        // get stores address
        $storeIds = $order->products->pluck('store_id')->unique();
        $stores = Store::select('id', 'name', 'email', 'phone', 'address', 'country', 'state', 'city', 'zip_code', 'shop_lat', 'shop_long', 'rating')->whereIn('id', $storeIds)->get();

        foreach ($stores as $store) {
            $store->order_status = DB::table('vendor_orders')->where('order_id', $orderId)->where('store_id', $store->id)->pluck('status')->first();
        }

        return response()->json([
            'success' => true,
            'message' => 'Oder Details',
            'data' => [
                'id' => $order->id,
                'code' => $order->code,
                'user_id' => $order->user_id,
                'rating' => $order->rating,
                'driver_id' => $order->driver_id,
                'driver_rating' => $order->driver_rating,
                'created_at' => $order->created_at,
                'stores' => $stores,
                'products' => $product,
                'order_status' => $status,
                'qty' => $order->total_qty,
                'amount' => number_format($acceptedProducts->sum(function ($product) {
                    return $product->price * $product->qty;
                }), 2),
                'delivery_fee' => number_format($order->shipping_amount, 2),
                'stores_count' => $order->stores_count,
                'final_amount' => number_format($acceptedProducts->sum(function ($product) {
                    return $product->price * $product->qty;
                }) + $order->shipping_amount, 2)
            ]
        ]);
    }

    // Re-order Order
    public function reOrder($order_id, PushNotificationService $pushNotificationService)
    {
        $order = Order::where('id', $order_id)->first();
        if (!$order) {
            return response()->json([
                'success' => false,
                'message' => 'Order not found.'
            ], 404);
        }

        $products = OrderProduct::where('order_id', $order_id)->get();

        if (!$products) {
            return response()->json([
                'success' => false,
                'message' => 'No products found in this order.'
            ], 404);
        }

        $customer = auth()->user();

        // check item stock status
        $outOfStockItems = []; {
            foreach ($products as $item) {
                $product = Product::find($item->product_id);
                if ($product && $product->qty) {
                    if ($product->qty < $item->qty) {
                        return response()->json([
                            'success' => false,
                            'message' => "Sorry, we only have {$product->qty} unit(s) of {$product->name} in stock. Please adjust your quantity.",
                        ], 400);
                    }

                    // update product quantity
                    $product->qty = $product->qty - $item->qty;
                }

                if ($product && $product->isOutOfStock()) {
                    $outOfStockItems[] = $product->id;
                }

                if ($product->quantity && $product->quantity === 0) {
                    $product->stock_status = 'out_of_stock';
                }
                $product->save();
            }
        }

        if (!empty($outOfStockItems)) {
            return response()->json([
                'message' => 'Some items in your cart are out of stock.',
                'out_of_stock_items' => $outOfStockItems
            ], 400);
        }

        // get order payment channel
        $paymentChannel = DB::table('payments')->where('order_id', $order_id)->pluck('payment_channel')->first();

        try {
            DB::beginTransaction();
            if ($paymentChannel == 'cod') {

                $orderToken = Str::random(25);
                $new_order_id = DB::table('ec_orders')->insertGetId([
                    'shipping_method' => 'default',
                    'amount' => $products->sum('price') * $products->sum('qty'),
                    'user_id' => $customer->id,
                    'sub_total' => $products->sum('price') * $products->sum('qty'),
                    'status' => 'pending',
                    'token' => $orderToken,
                    'is_finished' => 1,
                    'shipping_amount' => $order->shipping_amount,
                    'distance_in_km' => $order->distance_in_km,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);


                $payment = Payment::query()->create([
                    'amount' => $products->sum('price') * $products->sum('qty'),
                    'currency' => 'INR',
                    'payment_channel' => $paymentChannel,
                    'status' => PaymentStatusEnum::PENDING,
                    'payment_type' => 'confirm',
                    'order_id' => $new_order_id,
                    'charge_id' => Str::upper(Str::random(10)),
                    'user_id' => 0,
                    'customer_id' => $customer->id,
                    'customer_type' => Customer::class,
                ]);


                DB::table('ec_orders')->where('id', $new_order_id)->update([
                    'payment_id' => $payment->id,
                    'code' => '#202400' . $new_order_id
                ]);


                DB::table('ec_order_histories')->insert([
                    'order_id' => $new_order_id,
                    'user_id' => $customer->id,
                    'action' => 'create_order_from_payment_page',
                    'description' => 'Order was created from checkout page',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                foreach ($products as $item) {
                    $product = Product::query()->find($item->product_id);
                    DB::table('ec_order_product')->insert([
                        'order_id' => $new_order_id,
                        'qty' => $item->qty,
                        'price' => $product->price,
                        'product_id' => $item->product_id,
                        'product_name' => $product->name,
                        'product_image' => $product->original_product->image ?? null,
                        'weight' => $product->weight,
                        'tax_amount' => 0,
                        'product_type' => $product->product_type,
                        'store_id' => $product->store_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);
                }

                // get order address
                $orderAddress = DB::table('ec_order_addresses')->where('order_id', $order_id)->first();
                DB::table('ec_order_addresses')->insert([
                    'order_id' => $new_order_id,
                    'name' => $orderAddress->name,
                    'phone' => $orderAddress->phone,
                    'email' => $orderAddress->email,
                    'state' => $orderAddress->state,
                    'country' => $orderAddress->country,
                    'city' => $orderAddress->city,
                    'address' => $orderAddress->address,
                    'zip_code' => $orderAddress->zip_code,
                    'type' => 'shipping_address',
                    'lat' => $orderAddress->lat,
                    'long' => $orderAddress->long,
                ]);

                $stores = OrderProduct::where('order_id', $new_order_id)->distinct()->pluck('store_id');
                // Send push notification to all stores
                foreach ($stores as $store) {
                    // save suborders for stores
                    DB::table('vendor_orders')->insert([
                        'store_id' => $store,
                        'order_id' => $new_order_id,
                        'created_at' => now(),
                        'updated_at' => now(),
                    ]);

                    $store = Store::find($store);
                    if ($store) {
                        $orderCode = Order::where('id', $new_order_id)->pluck('code')->first();

                        // save notification in database
                        Notification::create([
                            'customer_id' => $store->customer->id,
                            'order_id' => $new_order_id,
                            'prefix' => 'vendor',
                            'title' => 'New Order',
                            'message' => "You have received a new order {$orderCode}",
                        ]);

                        if ($store->customer->player_id) {
                            $pushNotificationService->sendNotification(
                                "You have received a new order {$orderCode}",
                                [$store->customer->player_id],
                                null,
                                ['order_id' => $orderCode],
                                'New Order'
                            );
                        }
                    }
                }
            } else {
                //
            }
            DB::commit();

            return response()->json(['success' => true, 'message' => 'Order successfully placed.'], 200);
        } catch (\Exception $e) {
            DB::rollBack();

            return response()->json(['error' => 'Order could not be placed. Please try again.', 'msg' => $e], 500);
        }
    }

    // mark as favorite order
    public function makeFavorite($order_id)
    {
        $customer = auth()->user();

        $order = Order::where('id', $order_id)->where('user_id', $customer->id)->first();

        if (!$order) {
            return response()->json(['success' => false, 'message' => 'Order not found.'], 404);
        }

        $order->is_favorite = !$order->is_favorite;
        $order->save();

        $message = $order->is_favorite
            ? 'The order has been successfully marked as a favorite.'
            : 'The order has been successfully removed from your favorites.';

        return response()->json(['success' => true, 'message' => $message], 200);
    }
}
