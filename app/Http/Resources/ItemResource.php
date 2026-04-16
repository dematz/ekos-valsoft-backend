<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ItemResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'                  => $this->id,
            'name'                => $this->name,
            'sku'                 => $this->sku,
            'quantity'            => $this->quantity,
            'price'               => $this->price,
            'min_stock_threshold' => $this->min_stock_threshold,
            'status'              => $this->status,
            'category_id'         => $this->category_id,
            'category'            => new CategoryResource($this->whenLoaded('category')),
            'created_at'          => $this->created_at,
            'updated_at'          => $this->updated_at,
        ];
    }
}
