<?php

namespace Botble\Ecommerce\Http\Resources;

use Botble\Ecommerce\Models\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;
use Botble\Ecommerce\Http\Resources\ProductResource;

/**
 * @mixin ProductCategory
 */
class CategoryWithProductResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'category_name' => $this->name,
            'image' => $this->image ? Storage::url($this->image) : null,
            'products' => ProductResource::collection($this->whenLoaded('products')),
        ];
    }
}
