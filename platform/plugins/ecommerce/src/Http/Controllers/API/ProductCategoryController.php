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

class ProductCategoryController extends BaseController
{
    /**
     * Product Category
     *
     * @group Ecommerce
     */
    public function index(Request $request)
    {
        $data = ProductCategory::query()
            ->orderBy('order')
            ->orderByDesc('created_at')
            ->with('slugable')
            ->withCount('products')
            ->get();

        return (new BaseHttpResponse())
            ->setData(ProductCategoryResource::collection($data))
            ->toApiResponse();
    }

    /**
     * Product By Category ID
     *
     * @group Ecommerce
     */

    public function getByCategoryId(Request $request, $categoryId)
    {
        $category = ProductCategory::find($categoryId);

        if (!$category) {
            return response()->json(['success' => false, 'message' => 'Category not found'], 404);
        }

        // $products = Product::where('status', 'published')->whereHas('categories', function ($query) use ($categoryId) {
        //     $query->where('ec_product_category_product.category_id', $categoryId);
        // })->whereHas('store.customer', function ($query) {
        //     $query->whereNotNull('vendor_verified_at');
        // })
        // ->latest('id')
        // ->paginate(20);

        $products = Product::where('status', 'published')
            ->whereHas('categories', function ($query) use ($categoryId) {
                $query->where('ec_product_category_product.category_id', $categoryId);
            })
            ->whereHas('store.customer', function ($query) {
                $query->whereNotNull('vendor_verified_at');
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
            ->latest('id')
            ->paginate(20);

        foreach ($products as $product) {
            // add store details
            $store = Store::select('name', 'shop_lat', 'shop_long')->where('id', $product->store_id)->first();
            $product->store_name = $store->name;
            $product->shop_lat = $store->shop_lat;
            $product->shop_long = $store->shop_long;
            $product->category = $category->name;

            // add product images
            if (is_array($product->images)) {
                $product->images = array_map(function ($image_name) {
                    return Storage::url($image_name);
                }, $product->images);
            }
        }

        return response()->json([
            'success' => true,
            'data' => $products->items()
        ]);
    }

    /**
     * Product Detail By ID
     *
     * @group Ecommerce
     */

    public function getProductDetail(Request $request, $productId)
    {
        $product = Product::where('status', 'published')->whereHas('store.customer', function ($query) {
            $query->whereNotNull('vendor_verified_at');
        })->find($productId);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found or not published'], 404);
        }

        if (is_array($product->images)) {
            $product->images = array_map(function ($image_name) {
                return Storage::url($image_name);
            }, $product->images);
        }

        // add store details
        $store = Store::select('name', 'shop_lat', 'shop_long')->where('id', $product->store_id)->first();
        $product->store_name = $store->name;
        $product->shop_lat = $store->shop_lat;
        $product->shop_long = $store->shop_long;

        return response()->json([
            'success' => true,
            'data' => $product
        ]);
    }

    /**
     * Category With Products
     *
     * @group Ecommerce
     */

    public function getCategoriesWithProducts()
    {
        $categories = ProductCategory::whereHas('products', function ($query) {
            $query->where('status', 'published')
                ->whereHas('store.customer', function ($query) {
                    $query->whereNotNull('vendor_verified_at');
                });
        })->with(['products' => function ($query) {
            $query->where('status', 'published')
                ->whereHas('store.customer', function ($query) {
                    $query->whereNotNull('vendor_verified_at');
                })->orderBy('order')->limit(5);
        }])->limit(3)->get();

        return CategoryWithProductResource::collection($categories);
    }

    /**
     * Filters categories
     *
     * @group Blog
     */
    // public function getFilters(Request $request, CategoryInterface $categoryRepository)
    // {
    //     $filters = FilterCategory::setFilters($request->input());
    //     $data = $categoryRepository->getFilters($filters);

    //     return $this
    //         ->httpResponse()
    //         ->setData(CategoryResource::collection($data))
    //         ->toApiResponse();
    // }

    // /**
    //  * Get category by slug
    //  *
    //  * @group Blog
    //  * @queryParam slug Find by slug of category.
    //  */
    // public function findBySlug(string $slug)
    // {
    //     $slug = SlugHelper::getSlug($slug, SlugHelper::getPrefix(Category::class));

    //     if (! $slug) {
    //         return $this
    //             ->httpResponse()
    //             ->setError()
    //             ->setCode(404)
    //             ->setMessage('Not found');
    //     }

    //     $category = Category::query()
    //         ->with('slugable')
    //         ->where([
    //             'id' => $slug->reference_id,
    //             'status' => BaseStatusEnum::PUBLISHED,
    //         ])
    //         ->first();

    //     if (! $category) {
    //         return $this
    //             ->httpResponse()
    //             ->setError()
    //             ->setCode(404)
    //             ->setMessage('Not found');
    //     }

    //     return $this
    //         ->httpResponse()
    //         ->setData(new ListCategoryResource($category))
    //         ->toApiResponse();
    // }
}
