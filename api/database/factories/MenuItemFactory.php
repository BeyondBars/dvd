<?php

namespace Database\Factories;

use App\Models\MenuCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\MenuItem>
 */
class MenuItemFactory extends Factory
{
    public function definition(): array
    {
        return [
            'menu_category_id' => MenuCategory::factory(),
            'name' => ucwords($this->faker->unique()->words(2, true)),
            'description' => $this->faker->sentence(),
            'price_cents' => $this->faker->numberBetween(300, 2000),
            'sort_order' => 0,
            'is_available' => true,
        ];
    }

    public function soldOut(): static
    {
        return $this->state(['is_available' => false]);
    }
}
