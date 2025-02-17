<?php
namespace Botble\Ecommerce\Http\Resources;

use Botble\Marketplace\Models\Store;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

class ProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'product_name' => $this->name,
            'description' => strip_tags($this->description),
            'image' => $this->image ? Storage::url($this->image) : null,
            'store_id' => $this->store_id,
            'store_name' => Store::where('id', $this->store_id)->pluck('name')->first(),
            'quantity' => $this->quantity,
            'stock_status' => $this->stock_status,
            'price' => $this->price,
            'sale_price' => $this->sale_price,
            'rating' => $this->rating,
            'is_veg' => $this->is_veg
        ];
    }
}