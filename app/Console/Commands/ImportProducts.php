<?php

namespace App\Console\Commands;

use Botble\Ecommerce\Models\Product;
use Botble\Marketplace\Models\Store;
use Illuminate\Console\Command;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;

class ImportProducts extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'import:products {file}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Import products from a CSV file';

    public function __construct()
    {
        parent::__construct();
    }

    /**
     * Execute the console command.
     */
    public function handle()
    {
        $file = $this->argument('file');

        if (!file_exists($file)) {
            $this->error("File not found: $file");
            return;
        }


        $storeIds = [1, 2, 7, 9, 10, 11, 12, 13, 14, 15, 16, 17, 18];
        $categories = [1, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14, 15];

        // Open the CSV file
        if (($handle = fopen($file, 'r')) !== false) {
            $header = fgetcsv($handle); // Get the first row as the header


            // delete today added products
            // DB::table('ec_products')->where('created_at', '>=', now()->subDays(1))->delete();


            while (($row = fgetcsv($handle)) !== false) {
                $data = array_combine($header, $row); // Combine headers with row data

                // Validate the image URL
                if (filter_var($data['Image_Url'], FILTER_VALIDATE_URL)) {
                    // Generate a unique name for the image
                    $imageContents = @file_get_contents($data['Image_Url']);
                    if (!$imageContents) {
                        $productStatus = 'pending';
                    } else {
                        $productStatus = 'published';
                        // Save the product data into the database
                        $productID = DB::table('ec_products')->insertGetId([
                            'name' => $data['ProductName'],
                            'price' => $data['Price'],
                            'sale_price' => $data['DiscountPrice'],
                            'stock_status' => 'in_stock',
                            'product_type' => 'physical',
                            'status' => $productStatus,
                            'store_id' => Arr::random($storeIds, 1)[0],
                            'created_by_id' => Store::where('id', Arr::random($storeIds, 1)[0])->pluck('customer_id')->first(),
                            'created_by_type' => 'Botble\Ecommerce\Models\Customer',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // get product information
                        $product = Product::where('id', $productID)->first();
                        $product->sku = $product->generateSku();
                        $product->save();

                        // create slug
                        $slugKey = $this->createSlug($product->name);
                        $slug = DB::table('slugs')->insert([
                            'key' => $slugKey,
                            'reference_id' => $productID,
                            'reference_type' => 'Botble\Ecommerce\Models\Product',
                            'prefix' => 'products',
                            'created_at' => now(),
                            'updated_at' => now(),
                        ]);

                        // save categories
                        if ($categories) {
                            foreach ($categories as $category) {
                                DB::table('ec_product_category_product')->insert([
                                    'category_id' => $category,
                                    'product_id' => $productID
                                ]);
                            }
                        }

                        $imagePaths = [];
                        // get reference id
                        $customerId = Store::where('id', $product->store_id)->pluck('customer_id')->first();
                        $refId = DB::table('mp_vendor_info')->where('customer_id', $customerId)->pluck('id')->first();
                        $storeName = DB::table('slugs')->where('reference_id', $refId)->where('prefix', 'stores')->pluck('key')->first();

                        $imageName = Str::slug(pathinfo($data['Image_Url'], PATHINFO_FILENAME));;
                        $extension = pathinfo($data['Image_Url'], PATHINFO_EXTENSION);

                        $fileName = $imageName . '.' . $extension;
                        $path = public_path('storage/stores/' . $storeName);
                        $fileName = $this->ensureUniqueFileName($path, $imageName, $extension);

                        $originalPath = "{$path}/{$fileName}.{$extension}";


                        if (!File::exists($path)) {
                            File::makeDirectory($path, 0755, true);
                        }

                        
                        // $data['Image_Url']->move($path, "{$fileName}.{$extension}");
                        file_put_contents("{$originalPath}", $imageContents);
                        

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

                        // save images
                        $product->images = $imagePaths;
                        $product->save();

                        $this->info("Imported: {$data['ProductName']}");
                    }
                } else {
                    $this->error("Invalid image URL: {$data['Image_Url']}");
                }
            }

            fclose($handle);
        }

        $this->info('Import completed successfully!');
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
