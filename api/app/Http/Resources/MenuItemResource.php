<?php

namespace App\Http\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin \App\Models\MenuItem */
class MenuItemResource extends JsonResource
{
    public function toArray($request): array
    {
        return [
            'id' => $this->id,
            'menu_category_id' => $this->menu_category_id,
            'name' => $this->name,
            'description' => $this->description,
            'price_cents' => $this->price_cents,
            'sort_order' => $this->sort_order,
            'is_available' => $this->is_available,
            'option_groups' => $this->whenLoaded('optionGroups', fn () => $this->optionGroups->map(fn ($group) => [
                'id' => $group->id,
                'name' => $group->name,
                'min_choices' => $group->min_choices,
                'max_choices' => $group->max_choices,
                'options' => $group->options->map(fn ($option) => [
                    'id' => $option->id,
                    'name' => $option->name,
                    'price_cents' => $option->price_cents,
                ])->values(),
            ])->values()),
        ];
    }
}
