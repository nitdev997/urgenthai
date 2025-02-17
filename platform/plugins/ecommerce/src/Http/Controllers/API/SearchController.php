<?php

namespace Botble\Ecommerce\Http\Controllers\API;

use Botble\Base\Enums\BaseStatusEnum;
use Botble\Base\Http\Controllers\BaseController;
use Botble\Ecommerce\Http\Resources\ProductCategoryResource;
use Botble\Ecommerce\Models\ProductCategory;
use Botble\Slug\Facades\SlugHelper;
use Illuminate\Http\Request;
use Botble\Base\Http\Responses\BaseHttpResponse;
use Botble\Ecommerce\Models\Product;
use Botble\Ecommerce\Http\Resources\CategoryWithProductResource;
use Botble\Marketplace\Models\Store;
use Illuminate\Support\Facades\Storage;

class SearchController extends BaseController
{
    public function searchOnHome(Request $request)
    {
        $validated = $request->validate([
            'keyword' => 'required|string',
        ]);

        $customer = auth()->user();

        if (!$customer) {
            return response()->json([
                'success' => false,
                'message' => 'No authenticated customer found.',
            ], 401);
        }

        $keyword = $request->input('keyword');

        $products = Product::where('status', 'published')
            ->where(function ($query) use ($keyword) {
                $query->where('name', 'like', '%' . $keyword . '%')
                    ->orWhere('description', 'like', '%' . $keyword . '%');
            })
            ->when(request('sort_by') == 'rating_asc', function ($query) {
                $query->orderBy('rating', 'asc');  // Sort by rating (low to high)
            })
            ->when(request('sort_by') == 'rating_desc', function ($query) {
                $query->orderBy('rating', 'desc');  // Sort by rating (high to low)
            })
            ->when(request('sort_by') == 'price_asc', function ($query) {
                $query->orderByRaw('
                    CASE 
                        WHEN sale_price IS NOT NULL THEN sale_price
                        ELSE price 
                    END ASC
                '); // Sort by sale_price if exists, otherwise price (low to high)
            })
            ->when(request('sort_by') == 'price_desc', function ($query) {
                $query->orderByRaw('
                    CASE 
                        WHEN sale_price IS NOT NULL THEN sale_price
                        ELSE price 
                    END DESC
                '); // Sort by sale_price if exists, otherwise price (high to low)
            })
            ->when(request('food_type') == 'veg', function ($query) {
                $query->where('is_veg', true);  // Filter by veg products
            })
            ->when(request('food_type') == 'non_veg', function ($query) {
                $query->where('is_veg', false);  // Filter by non-veg products
            })
            ->paginate(20);

        foreach ($products as $product) {
            if (is_array($product->images)) {
                $product->images = array_map(function ($image_name) {
                    return Storage::url($image_name);
                }, $product->images);
            }
        }
        $results = $products->map(function ($product) {
            $storeName = Store::where('id', $product->store_id)->pluck('name')->first();
            return [
                'id' => $product->id,
                'name' => $product->name,
                'description' => $product->description,
                'image' => $product->image,
                'price' => number_format($product->price, 2),
                'sale_price' => number_format($product->sale_price, 2),
                'rating' => $product->rating,
                'is_veg' => $product->is_veg,
                'store_name' => $storeName,
                'stock_status' => $product->stock_status
            ];
        });

        return response()->json($results);
    }
}
