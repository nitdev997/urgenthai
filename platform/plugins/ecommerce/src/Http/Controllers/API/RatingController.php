<?php

namespace Botble\Ecommerce\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Botble\Ecommerce\Models\OrderProduct;
use Botble\Marketplace\Models\Store;
use Illuminate\Http\Request;
use Botble\Driver\Models\Driver;
use Botble\Ecommerce\Models\Order;

class RatingController extends Controller
{
    // create rating
    public function rateProduct(Request $request)
    {
        $request->validate([
            'id' => 'required|integer|exists:ec_order_product,id',
            'rating' => 'required|numeric|between:1,5',
        ], [
            'id.required' => 'The order product ID is required.',
            'id.exists' => 'The specified order product does not exist.',
            'rating.required' => 'A rating is required.',
            'rating.between' => 'The rating must be between 1 and 5.',
        ]);

        /**
        * individual order items rating
        */
        $orderProduct = OrderProduct::find($request->id);

        $orderProduct->rating = $request->rating;
        $orderProduct->save();

        /**
         * order rating from average of order items rating
         */
        $totalRating = OrderProduct::where('order_id', $orderProduct->order_id)->sum('rating');
        $orderProduct->order->rating = $totalRating / OrderProduct::where('order_id', $orderProduct->order_id)->count();
        $orderProduct->order->save();

        /**
         * Product rating from averate of order items rating
         */
        $totalRating = OrderProduct::where('product_id', $orderProduct->product_id)->sum('rating');
        $orderProduct->product->rating = $totalRating / OrderProduct::where('product_id', $orderProduct->product_id)->count();
        $orderProduct->product->save();

        /**
         * Store rating from averate of store products rating
         */
        $totalRating = OrderProduct::where('store_id', $orderProduct->store_id)->sum('rating');
        $store = Store::find($orderProduct->store_id);
        $store->rating = $totalRating / OrderProduct::where('store_id', $orderProduct->store_id)->count();
        $store->save();

        return response()->json([
            'success' => true,
            'message' => 'Rating has been updated successfully.',
        ], 200);
    }

    // Give rating to driver
    public function rateDriver(Request $request) {
      $request->validate([
          'order_id' => 'required|integer|exists:ec_orders,id',
          'driver_id' => 'required|integer|exists:drivers,id',
          'rating' => 'required|numeric|between:1,5',
      ], [
          'rating.required' => 'A rating is required.',
          'rating.between' => 'The rating must be between 1 and 5.',
      ]);

      // check if driver are associated with the order
      $order = Order::where('id', $request->order_id)->where('driver_id', $request->driver_id)->first();
      if(!$order) {
        return response()->json([
          'success' => false,
          'message' => 'Order not found or this driver is not associated with this order'
        ], 400);
      }

      // check if rating is already given
      if($order->driver_rating && $order->driver_rating > 0){
        return response()->json([
          'success' => false,
          'message' => 'Ratings already submited.'
        ]);
      }

      // give rating
      $order->driver_rating = $request->rating;
      $order->save();

      // calculate average of rating of driver
      $driverOrders = Order::where('driver_id', $request->driver_id)->select('id', 'driver_rating')->get();
      $sumOfRatings = $driverOrders->sum('driver_rating');
      $numberOfRatings = $driverOrders->count();
      $averateRating = $sumOfRatings / $numberOfRatings;

      // get driver
      $driver = Driver::find($request->driver_id);

      // update average rating
      $driver->rating = $averateRating;
      $driver->save();

      return response()->json([
        'success' => true,
        'messaeg' => 'Rating has been updated successfully.'
      ], 200);
    }
}
