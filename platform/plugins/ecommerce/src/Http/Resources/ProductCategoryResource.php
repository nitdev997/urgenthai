<?php

namespace Botble\Ecommerce\Http\Resources;

use Botble\Ecommerce\Models\ProductCategory;
use Illuminate\Http\Resources\Json\JsonResource;
use Illuminate\Support\Facades\Storage;

/**
 * @mixin ProductCategory
 */
class ProductCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'image' =>  Storage::url($this->image)
        ];
    }
}
