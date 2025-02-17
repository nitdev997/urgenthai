<?php

namespace Botble\Ecommerce\Http\Controllers\API;

use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Http\Controllers\Fronts\PublicCartController;
use Illuminate\Http\Request;
use Botble\Ecommerce\Http\Requests\CartRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Storage;
use Botble\Ecommerce\Facades\Cart;
use Botble\Ecommerce\Models\Product;
use Botble\Marketplace\Models\Store;
use DB;
use Illuminate\Support\Facades\Cookie;

class CartController extends PublicCartController
{

    /**
     * Add To Cart
     *
     * @group Cart
     */
    public function store(CartRequest $request): JsonResponse
    {
        $customer = auth()->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated customer found.',
            ], 401);
        }

        $productId = $request->input('id');
        $quantity = $request->input('qty', 1);

        $product = Product::find($productId);

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found',
            ], 404);
        }

        // check quantity
        if ($product->quantity && $quantity > $product->quantity) {
            return response()->json([
                'success' => false,
                'message' => "Sorry, we only have {$product->quantity} unit(s) of {$product->name} in stock. Please adjust your quantity.",
            ], 400);
        }

        if ($product->stock_status != "in_stock") {
            return response()->json([
                'success' => false,
                'message' => "Unfortunately, {$product->name} is not currently in stock",
            ], 400);
        };

        /**
        * check if its a Wine & Alcohol category
        * ristrict user to only add one Quantity of Wine & Alcohol category
        * check if already have Wine & Alcohol category item in cart
        */
        $getCategoryId = DB::table('ec_product_categories')->where('name', 'like', '%Wine & Alcohol%')->pluck('id')->first();
        $isAlcohal = DB::table('ec_product_category_product')->where('product_id', $productId)->where('category_id', $getCategoryId)->exists();

        if($isAlcohal) {
          $isAlreadyInCart = DB::table('temporary_carts')->where('customer_id', auth()->user()->id)->where('product_id', $productId)->exists();
          if($isAlreadyInCart) {
            return response()->json([
              'success' => false,
              'message' => "Remove the existing 'Alcohol & Wine' item before adding a new one."
            ], 400);
          } else {
            if($quantity > 1){
              return response()->json([
                'success' => false,
                'message' => "Only one 'Alcohol & Wine' item can be added."
              ], 400);
            }
          }
        }

        // Calculate total price for this product and quantity
        $totalPrice = ($product->sale_price && $product->sale_price > 0 ? $product->sale_price : $product->price) * $quantity;

        // Check if the product is already in the customer's cart
        $cartItem = DB::table('temporary_carts')
            ->where('customer_id', $customer->id)  // Use customer_id to relate to the cart
            ->where('product_id', $product->id)
            ->first();

        if ($cartItem) {
            $totalPrice += $cartItem->price;
            DB::table('temporary_carts')->updateOrInsert(
                ['customer_id' => $customer->id, 'product_id' => $product->id],
                [
                    'quantity' => DB::raw("quantity + $quantity"),
                    'price' => $totalPrice,
                    'updated_at' => now(),
                ]
            );
        } else {
            DB::table('temporary_carts')->insert([
                'customer_id' => $customer->id,
                'product_id' => $product->id,
                'quantity' => $quantity,
                'price' => $totalPrice,
                'store_id' => $product->store_id,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item added to cart',
        ], 200);
    }

    /**
     * Remove Item Quantity
     *
     * @group Cart
     */
    public function remove(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'id' => 'required',
            'type' => 'required',
        ]);

        $customer = auth()->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated customer found.',
            ], 401);
        }

        $productId = $request->input('id');

        $cartItem = \DB::table('temporary_carts')
            ->where('customer_id', $customer->id)
            ->where('product_id', $productId)
            ->first();

        if (!$cartItem) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found in cart.'
            ], 404);
        }

        if($request->type == 1) {
            $newQuantity = $cartItem->quantity - 1;
        }else {
            $newQuantity = 0;
        }

        if ($newQuantity <= 0) {
            \DB::table('temporary_carts')
                ->where('customer_id', $customer->id)
                ->where('product_id', $productId)
                ->delete();
        } else {
            $product = Product::findOrFail($productId);
            $totalPrice = $product->price * $newQuantity;

            \DB::table('temporary_carts')
                ->where('customer_id', $customer->id)
                ->where('product_id', $productId)
                ->update([
                    'quantity' => $newQuantity,
                    'price' => $totalPrice,
                    'updated_at' => now(),
                ]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Item quantity updated successfully.',
        ], 200);
    }



    /**
     * Get Cart Items
     *
     * @group Cart
     */

    public function getCartItem(Request $request): JsonResponse
    {
        $customer = auth()->user();

        // If there's no authenticated customer, return an error
        if (!$customer) {
            return response()->json(['success' => false, 'message' => 'No authenticated customer found.'], 401);
        }

        $total_price = 0;
        $delivery_fee = 140;
        $totalDistance = 0;
        $stores = [];

        $cartItems = \DB::table('temporary_carts')
            ->where('temporary_carts.customer_id', $customer->id)
            ->join('ec_products', 'temporary_carts.product_id', '=', 'ec_products.id')
            ->join('mp_stores', 'ec_products.store_id', '=', 'mp_stores.id')
            ->select('ec_products.id', 'ec_products.name', 'ec_products.stock_status', 'temporary_carts.price', 'temporary_carts.quantity', 'ec_products.images as image', 'ec_products.store_id', 'mp_stores.name as store_name')
            ->get();
        foreach ($cartItems as $item) {
            if (is_string($item->image)) {
                $item->image = json_decode($item->image, true);
            }

            if (is_array($item->image) && !empty($item->image)) {
                $item->image = Storage::url($item->image[0]);
            } elseif (is_string($item->image)) {
                $item->image = Storage::url($item->image);
            }

            $total_price = $total_price + $item->price;

            // Check if the store_id already exists in the stores array
            if (!isset($stores[$item->store_id])) {
                // Retrieve the store details from the database
                $store = Store::select('id', 'name', 'shop_lat', 'shop_long')
                    ->where('id', $item->store_id)
                    ->first();

                // If the store exists, add the store details to the $stores array
                if ($store) {
                    $stores[$item->store_id] = [
                        'id' => $store->id,
                        'name' => $store->name,
                        'latitude' => $store->shop_lat,
                        'longitude' => $store->shop_long,
                    ];
                }
            }
        }

        // dd($stores);
        if ($cartItems->isEmpty()) {
            return response()->json(['success' => true, 'message' => 'Cart is empty', 'data' => $cartItems], 200);
        }


        $customerArress = DB::table('ec_customer_addresses')->select('lat', 'long')->where('customer_id', $customer->id)->where('is_default', 1)->first();
        $customerLat = $customerArress->lat ?? 0;
        $customerLon = $customerArress->long ?? 0;

        // First, calculate the distance from Customer to the first store (if any stores are present)
        if (count($stores) > 0) {
            $firstStore = reset($stores);
            // dd($firstStore);
            $distance = $this->getDistance($customerLat, $customerLon, $firstStore['latitude'], $firstStore['longitude']);
            if(isset($distance['paths'][0]['distance'])){
                $totalDistance += ($distance['paths'][0]['distance']);
            }
        }

        // Calculate distances between consecutive stores
        $storeKeys = array_keys($stores);
        for ($i = 0; $i < count($storeKeys) - 1; $i++) {
            $currentStoreId = $storeKeys[$i];
            $nextStoreId = $storeKeys[$i + 1];

            $distance = $this->getDistance($stores[$currentStoreId]['latitude'], $stores[$currentStoreId]['longitude'], $stores[$nextStoreId]['latitude'], $stores[$nextStoreId]['longitude']);
            if(isset($distance['paths'][0]['distance'])){
                $totalDistance += ($distance['paths'][0]['distance']);
            }
        }

        $total_distance = number_format(($totalDistance / 1000), 2);
        if ($total_distance <= 2) {
            $delivery_fee = 20;
        } else {
            $delivery_fee = (($total_distance - 2) * 7) + 20;
        }

        foreach ($cartItems as $item) {
            $item->price = number_format($item->price, 2);
            $item->stock_status = $item->stock_status;
        }

        return response()->json([
            'success' => true,
            'message' => 'Cart items',
            'data' => [
                'products' => $cartItems,
                'amount' => number_format($total_price, 2),
                'distance' => $total_distance . ' km',
                'delivery_fee' => number_format($delivery_fee, 2),
                'final_amount' => number_format($total_price + $delivery_fee, 2)
            ]
        ], 200);
    }

    public function getDistance($lat1, $lon1, $lat2, $lon2)
    {


        $curl = curl_init();

        curl_setopt_array($curl, array(
            CURLOPT_URL => 'https://graphhopper.com/api/1/route?key=c96fe07f-444b-42f2-b1c8-7216e89f52ed',
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_ENCODING => '',
            CURLOPT_MAXREDIRS => 10,
            CURLOPT_TIMEOUT => 0,
            CURLOPT_FOLLOWLOCATION => true,
            CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
            CURLOPT_CUSTOMREQUEST => 'POST',
            CURLOPT_POSTFIELDS => json_encode([
                "points" => [
                    [$lon1, $lat1],
                    [$lon2, $lat2]
                ],
                "snap_preventions" => [
                    "motorway",
                    "ferry",
                    "tunnel"
                ],
                "details" => [
                    "road_class",
                    "surface"
                ],
                "profile" => "car",
                "locale" => "en",
                "instructions" => false,
                "calc_points" => true,
                "points_encoded" => false
            ]),
            CURLOPT_HTTPHEADER => array(
                'Accept: application/json',
                'Content-Type: application/json'
            ),
        ));

        $response = curl_exec($curl);

        curl_close($curl);
        return json_decode($response, true);
    }
}
