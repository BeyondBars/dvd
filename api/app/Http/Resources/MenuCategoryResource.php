<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MenuCategory */
class MenuCategoryResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'description' => $this->description,
            'sort_order' => $this->sort_order,
            'is_visible' => $this->is_visible,
            'items' => MenuItemResource::collection($this->whenLoaded('items')),
        ];
    }
}
