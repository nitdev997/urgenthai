<?php

namespace Database\Seeders;

use Botble\Ecommerce\Models\Product;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class dummyProductSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $response = Http::get('https://fakestoreapi.com/products');

        if ($response->ok()) {
            $products = $response->json();

            foreach ($products as $product) {
                
                // Save each product to the database
                $product = Product::create([
                    'name' => $product['title'],
                    'description' => $product['description'],
                    'price' => $product['price'],
                    'stock_status' => 'in_stock',
                    'product_type' => 'physical',
                    'created_by_type' => "Botble\Ecommerce\Models\Customer",
                    'status' => 'published',
                    'created_by_id' => 31,
                    'store_id' => 15,
                    'approved_by' => 2,
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                $product->sku = $product->generateSku();
                $product->save();

                // create slug
                $slug = $this->createSlug($product->name);

                // create slug
                $slug = DB::table('slugs')->insert([
                    'key' => $slug,
                    'reference_id' => $product->id,
                    'reference_type' => 'Botble\Ecommerce\Models\Product',
                    'prefix' => 'products',
                    'created_at' => now(),
                    'updated_at' => now(),
                ]);

                // save categories
                $categories = [1, 2, 3, 4, 5, 6, 7, 8, 9, 10, 11, 12, 13, 14];
                foreach ($categories as $category) {
                    DB::table('ec_product_category_product')->insert([
                        'category_id' => $category,
                        'product_id' => $product->id
                    ]);
                }
            }
        } else {
            $this->command->error('Failed to fetch data from FakeStore API');
        }
    }


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
}
