<?php

namespace Botble\Marketplace\Http\Controllers\API;

use App\Http\Controllers\Controller;
use Botble\Ecommerce\Enums\ProductTypeEnum;
use Botble\Ecommerce\Enums\StockStatusEnum;
use Botble\Ecommerce\Models\Order;
use Botble\Ecommerce\Models\OrderProduct;
use Botble\Ecommerce\Models\Product;
use Botble\Marketplace\Models\Store;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Support\Str;

use function Laravel\Prompts\table;

class ProductController extends Controller
{

    /**
     * Get products
     */
    public function index(Request $request)
    {
        $storeId = DB::table('mp_stores')->where('customer_id', $request->user()->id)->pluck('id')->first();
        $products = Product::with('categories:id,name')->where('store_id', $storeId)->latest('id')->paginate(20);

        if ($products->count() < 1) {
            return response()->json([
                'success' => false,
                'message' => 'No products found',
                'data' => $products->items()
            ], 200);
        }

        // add images path
        foreach ($products as $product) {
            if (is_array($product->images)) {
                $product->images = array_map(function ($image_name) {
                    return Storage::url($image_name);
                }, $product->images);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Products retrieved successfully',
            'data' => $products->items()
        ], 200);
    }

    /**
     * Product Detail By ID
     */
    public function view($product_id)
    {
        $storeId = DB::table('mp_stores')->where('customer_id', auth()->user()->id)->pluck('id')->first();
        $product = Product::with('categories:id,name')->where('id', $product_id)->where('store_id', $storeId)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found or not published'
            ], 404);
        }

        if (is_array($product->images)) {
            $product->images = array_map(function ($image_name) {
                return Storage::url($image_name);
            }, $product->images);
        }

        return response()->json([
            'success' => true,
            'message' => 'Product retrieved successfully',
            'data' => $product
        ], 200);
    }

    // store product
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:250',
            'description' => 'string|nullable',
            'price' => 'numeric|nullable|min:0|max:100000000000',
            'sale_price' => 'numeric|nullable|min:0|max:100000000000',
            'wide' => 'numeric|nullable|min:0|max:100000000',
            'wide_unit' => 'nullable|string',
            'height' => 'numeric|nullable|min:0|max:100000000',
            'height_unit' => 'nullable|string',
            'weight' => 'numeric|nullable|min:0|max:100000000',
            'weight_unit' => 'nullable|string',
            'length' => 'numeric|nullable|min:0|max:100000000',
            'length_unit' => 'nullable|string',
            'stock_status' => Rule::in(StockStatusEnum::values()),
            'images' => 'sometimes|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'quantity' => 'numeric|nullable|min:0|max:100000000',
            'product_type' => Rule::in(ProductTypeEnum::values()),
            'categories' => 'nullable|array',
            'categories.*' => 'nullable|exists:ec_product_categories,id',
            'is_veg' => 'boolean|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        if ($request->sale_price && $request->sale_price >= $request->price) {
            return response()->json(['success' => false, 'message' => 'Sale price must be less than price.'], 422);
        }


        Event::listen('eloquent.creating: Botble\Ecommerce\Models\Product', function ($product) {
            unset($product->created_by_type); // Temporarily unset auto-set value
        });

        // create slug
        $slug = $this->createSlug($request->name);

        // get reference id
        $refId = DB::table('mp_vendor_info')->where('customer_id', $request->user()->id)->pluck('id')->first();
        $storeName = DB::table('slugs')->where('reference_id', $refId)->where('prefix', 'stores')->pluck('key')->first();

        // upload images
        $imagePaths = [];
        if ($request->has('images')) {
            foreach ($request->file('images') as $image) {
                if ($image) {
                    $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                    $originalName = Str::slug($originalName);
                    $extension = $image->getClientOriginalExtension();

                    $fileName = $originalName . '.' . $extension;

                    $path = public_path('storage/stores/' . $storeName);
                    $fileName = $this->ensureUniqueFileName($path, $originalName, $extension);

                    $originalPath = "{$path}/{$fileName}.{$extension}";
                    $image->move($path, "{$fileName}.{$extension}");



                    $resizedPaths = [
                        [
                            'sWidth' => 150,
                            'sHeight' => 150,
                            'sPath' => public_path("storage/stores/{$storeName}/{$fileName}-150x150.{$extension}")
                        ],
                        [
                            'sWidth' => 400,
                            'sHeight' => 400,
                            'sPath' => public_path("storage/stores/{$storeName}/{$fileName}-400x400.{$extension}")
                        ],
                        [
                            'sWidth' => 800,
                            'sHeight' => 800,
                            'sPath' => public_path("storage/stores/{$storeName}/{$fileName}-800x800.{$extension}")
                        ],
                    ];

                    foreach ($resizedPaths as $sPath) {
                        $this->resizeImage($originalPath, $sPath['sPath'], $sPath['sWidth'], $sPath['sHeight']);
                    }

                    $imagePaths[] = "stores/{$storeName}/{$fileName}.{$extension}";
                }
            }
        }

        $storeId = DB::table('mp_stores')->where('customer_id', $request->user()->id)->pluck('id')->first();

        // Update product fields
        if($request->quantity == 0) {
            $stock_status = 'out_of_stock';
        } else {
            $stock_status = $request->stock_status ?? 'in_stock';
        }

        // create product
        $productID = DB::table('ec_products')->insertGetId([
            'name' => $request->name,
            'description' => $request->description,
            'images' => json_encode($imagePaths),
            'price' => $request->price,
            'sale_price' => $request->sale_price,
            'wide' => $request->wide,
            'wide_unit' => $request->wide_unit,
            'height' => $request->height,
            'height_unit' => $request->height_unit,
            'weight' => $request->weight,
            'weight_unit' => $request->weight_unit,
            'length' => $request->length,
            'length_unit' => $request->length_unit,
            'stock_status' => $stock_status,
            'quantity' => $request->quantity,
            'with_storehouse_management' => ($request->quantity && $request->quantity > 0) ? 1 : 0,
            'product_type' => $request->product_type ?? 'physical',
            'created_by_type' => "Botble\Ecommerce\Models\Customer",
            'status' => 'pending',
            'created_by_id' => $request->user()->id,
            'store_id' => $storeId,
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
            'is_veg' => $request->is_veg ?? false
        ]);

        // get product information
        $product = Product::where('id', $productID)->first();
        $product->sku = $product->generateSku();
        $product->save();

        // create slug
        $slug = DB::table('slugs')->insert([
            'key' => $slug,
            'reference_id' => $productID,
            'reference_type' => 'Botble\Ecommerce\Models\Product',
            'prefix' => 'products',
            'created_at' => Carbon::now(),
            'updated_at' => Carbon::now(),
        ]);

        // save categories
        if ($request->categories) {
            foreach ($request->categories as $category) {
                DB::table('ec_product_category_product')->insert([
                    'category_id' => $category,
                    'product_id' => $productID
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Product created successfully'
        ]);
    }

    /**
     * Update a product
     */
    public function update(Request $request, $id)
    {
        $validator = Validator::make($request->all(), [
            'name' => 'string|max:250',
            'description' => 'string|nullable',
            'price' => 'numeric|nullable|min:0|max:100000000000',
            'sale_price' => 'numeric|nullable|min:0|max:100000000000',
            'wide' => 'numeric|nullable|min:0|max:100000000',
            'wide_unit' => 'nullable|string',
            'height' => 'numeric|nullable|min:0|max:100000000',
            'height_unit' => 'nullable|string',
            'weight' => 'numeric|nullable|min:0|max:100000000',
            'weight_unit' => 'nullable|string',
            'length' => 'numeric|nullable|min:0|max:100000000',
            'length_unit' => 'nullable|string',
            'stock_status' => Rule::in(StockStatusEnum::values()),
            'images' => 'sometimes|array',
            'images.*' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'quantity' => 'numeric|nullable|min:0|max:100000000',
            'product_type' => Rule::in(ProductTypeEnum::values()),
            'categories' => 'nullable|array',
            'categories.*' => 'nullable|exists:ec_product_categories,id',
            'is_veg' => 'boolean|nullable'
        ]);

        if ($validator->fails()) {
            return response()->json(['success' => false, 'message' => $validator->errors()], 422);
        }

        $product = Product::find($id);

        if (!$product) {
            return response()->json(['success' => false, 'message' => 'Product not found'], 404);
        }

        $storeName = DB::table('slugs')->where('reference_id', $product->store_id)->where('prefix', 'stores')->pluck('key')->first();

        // Update images if provided
        $imagePaths = $product->images ?? [];
        if ($request->has('images')) {
            foreach ($request->file('images') as $image) {
                if ($image) {
                    $originalName = pathinfo($image->getClientOriginalName(), PATHINFO_FILENAME);
                    $originalName = Str::slug($originalName);
                    $extension = $image->getClientOriginalExtension();

                    $fileName = $originalName . '.' . $extension;

                    $path = public_path('storage/stores/' . $storeName);
                    $fileName = $this->ensureUniqueFileName($path, $originalName, $extension);

                    $originalPath = "{$path}/{$fileName}.{$extension}";
                    $image->move($path, "{$fileName}.{$extension}");

                    $resizedPaths = [
                        [
                            'sWidth' => 150,
                            'sHeight' => 150,
                            'sPath' => public_path("storage/stores/{$storeName}/{$fileName}-150x150.{$extension}")
                        ],
                        [
                            'sWidth' => 400,
                            'sHeight' => 400,
                            'sPath' => public_path("storage/stores/{$storeName}/{$fileName}-400x400.{$extension}")
                        ],
                        [
                            'sWidth' => 800,
                            'sHeight' => 800,
                            'sPath' => public_path("storage/stores/{$storeName}/{$fileName}-800x800.{$extension}")
                        ],
                    ];

                    foreach ($resizedPaths as $sPath) {
                        $this->resizeImage($originalPath, $sPath['sPath'], $sPath['sWidth'], $sPath['sHeight']);
                    }

                    $imagePaths[] = "stores/{$storeName}/{$fileName}.{$extension}";
                }
            }
            $product->images = $imagePaths;
        }
        
        // Update product fields
        if($request->quantity == 0) {
            $stock_status = 'out_of_stock';
        } else {
            $stock_status = $request->stock_status ?? $product->stock_status;
        }
        $product->update([
            'name' => $request->name ?? $product->name,
            'description' => $request->description ?? $product->description,
            'price' => $request->price ?? $product->price,
            'sale_price' => $request->sale_price ?? $product->sale_price,
            'wide' => $request->wide ?? $product->wide,
            'wide_unit' => $request->wide_unit ?? $product->wide_unit,
            'height' => $request->height ?? $product->height,
            'height_unit' => $request->height_unit ?? $product->height_unit,
            'weight' => $request->weight ?? $product->weight,
            'weight_unit' => $request->weight_unit ?? $product->weight_unit,
            'length' => $request->length ?? $product->length,
            'length_unit' => $request->length_unit ?? $product->length_unit,
            'stock_status' => $stock_status,
            'quantity' => $request->quantity ?? $product->quantity,
            'product_type' => $request->product_type ?? $product->product_type,
            'updated_at' => Carbon::now(),
            'is_veg' => $request->is_veg ?? $product->is_veg
        ]);

        // Update categories if provided
        if ($request->categories) {
            DB::table('ec_product_category_product')->where('product_id', $id)->delete();
            foreach ($request->categories as $category) {
                DB::table('ec_product_category_product')->insert([
                    'category_id' => $category,
                    'product_id' => $id
                ]);
            }
        }

        return response()->json([
            'success' => true,
            'message' => 'Product updated successfully'
        ]);
    }


    /**
     * Destroy products
     */
    public function destroy($product_id)
    {
        $product = Product::where('id', $product_id)->where('created_by_id', auth()->user()->id)->first();

        if (!$product) {
            return response()->json([
                'success' => false,
                'message' => 'Product not found.'
            ], 404);
        }

        // check if product is in order
        $orderId = OrderProduct::where('product_id', $product_id)->pluck('order_id')->first();
        if ($orderId) {
            // check order status
            $orderStatus = Order::where('id', $orderId)->pluck('status')->first();
            if ($orderStatus != 'completed') {
                return response()->json([
                    'success' => false,
                    'message' => 'Product is in an order and cannot be deleted.'
                ], 400);
            }
        }

        DB::table('slugs')->where('prefix', 'products')->where('reference_id', $product->id)->delete();
        DB::table('ec_product_category_product')->where('product_id', $product->id)->delete();

        $product->delete();

        return response()->json([
            'success' => true,
            'message' => 'Product deleted successfully'
        ]);
    }

    // create slug
    function createSlug($title)
    {
        // Generate the initial slug
        $slug = Str::slug($title);

        // Check if the slug exists in the database
        $originalSlug = $slug;
        $count = 1;

        while (DB::table('slugs')->where('key', $slug)->exists()) {
            $slug = "{$originalSlug}-{$count}";
            $count++;
        }

        return $slug;
    }

    /**
     * Unique filename for product
     */
    private function ensureUniqueFileName($path, $originalName, $extension)
    {
        $fileName = "{$originalName}";
        $counter = 1;

        // Check if the file already exists
        while (file_exists("{$path}/{$fileName}.{$extension}")) {
            $fileName = "{$originalName}-{$counter}";
            $counter++;
        }

        return $fileName;
    }

    // Function to resize an image
    function resizeImage($sourcePath, $destinationPath, $newWidth, $newHeight)
    {
        // Check if the source file exists
        if (!file_exists($sourcePath)) {
            die("Source file does not exist.");
        }

        // Get original image dimensions and type
        list($width, $height, $type) = getimagesize($sourcePath);

        // Create a new image from the source file based on type
        switch ($type) {
            case IMAGETYPE_JPEG:
                $sourceImage = imagecreatefromjpeg($sourcePath);
                break;
            case IMAGETYPE_PNG:
                $sourceImage = imagecreatefrompng($sourcePath);
                break;
            case IMAGETYPE_GIF:
                $sourceImage = imagecreatefromgif($sourcePath);
                break;
            default:
                die("Unsupported image type.");
        }

        // Create a new true color image with the desired dimensions
        $resizedImage = imagecreatetruecolor($newWidth, $newHeight);

        // Preserve transparency for PNG and GIF images
        if ($type == IMAGETYPE_PNG || $type == IMAGETYPE_GIF) {
            imagealphablending($resizedImage, false);
            imagesavealpha($resizedImage, true);
        }

        // Resize the original image into the new one
        imagecopyresampled($resizedImage, $sourceImage, 0, 0, 0, 0, $newWidth, $newHeight, $width, $height);

        // Save the resized image to the destination path
        switch ($type) {
            case IMAGETYPE_JPEG:
                imagejpeg($resizedImage, $destinationPath, 90); // Quality: 90
                break;
            case IMAGETYPE_PNG:
                imagepng($resizedImage, $destinationPath);
                break;
            case IMAGETYPE_GIF:
                imagegif($resizedImage, $destinationPath);
                break;
        }

        // Free up memory
        imagedestroy($sourceImage);
        imagedestroy($resizedImage);

        return true;
    }
}
