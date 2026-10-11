<?php

namespace Database\Seeders;

use App\Models\MenuCategory;
use Illuminate\Database\Seeder;

/**
 * The menu as it was on desivideshidiner.com.au when ordering was added. Only seeds an empty menu,
 * so it is safe to run on a live database: php artisan db:seed --class=MenuSeeder
 */
class MenuSeeder extends Seeder
{
    /** Category name => [description, [[item, price in cents, description], ...]] */
    public const MENU = [
        'Breakfast' => [
            'Desi Paranthas (2) with different stuffings & sides (Butter / Dahi / Pickles)',
            [
                ['Aloo Pyaaj Paranthas', 1299, 'Potatoes, Onion, Green Chillies, Coriander, Ginger, Ajwain, Cumin Seeds, Salt'],
                ['Gobhi Paranthas', 1299, 'Cauliflower, Green Chillies, Coriander, Ginger, Cumin Seeds, Salt'],
                ['Mooli Paranthas', 1299, 'Reddish, Green Chillies, Coriander, Ginger, Cumin Seeds, Salt'],
                ['Paneer Paranthas', 1399, 'Cottage Cheese, Green Chillies, Coriander, Ginger, Cumin Seeds, Salt'],
                ['Mix Paranthas', 1399, 'Mixed Vegetables, Green Chillies, Coriander, Ginger, Cumin Seeds, Salt'],
                ['Parantha Combo (Chaas / Lassi)', 1799, null],
                ['Plain Paranthas x 3 with Paneer bhurji', 1399, null],
            ],
        ],
        'Snacks & Light Meals' => [
            null,
            [
                ['Poha', 799, 'Light and flavourful flattened rice, sautéed with peanuts and curry leaves'],
                ['Poha & Chai Combo', 1099, 'Poha served with a hot cup of Desi Chai'],
                ['Veg Pakora (5 pcs)', 799, 'Freshly made vegetable fritters'],
                ['Samosa (2 pcs)', 799, 'Crispy pastry filled with potatoes & peas'],
                ['Desi Patty (Aloo / Paneer / Mix)', 599, 'Puff Pastry with different stuffings'],
                ['Desi Sandwiches', 1199, 'Fresh bread with assorted filling and chutney'],
            ],
        ],
        'Lunch' => [
            'Rice Combos: Rice Combo with any Curry. +$2.99 for Large Combo',
            [
                ['Black Daal & Rice', 899, 'Black lentils, Red Kidney Bean cooked with aromatic spices (Veg | Gluten Free | Contains Dairy)'],
                ['Rajma & Rice', 899, 'Red Kidney beans cooked with aromatic & robust Punjabi spices. (Vegan | Gluten-Free)'],
                ['Channe & Rice', 899, 'White chickpeas simmered in a tangy gravy with aromatic spices. (Vegan | Gluten-Free)'],
            ],
        ],
        'Sides' => [
            null,
            [
                ['Butter (1 cube)', 99, null],
                ['Pickle', 199, null],
                ['Dahi (Curd)', 299, null],
                ['Fries (Large)', 599, null],
            ],
        ],
        'Beverages' => [
            null,
            [
                ['Desi Chai', 499, 'Traditional Indian Tea'],
                ['Sweet Lassi', 699, 'Traditional Indian Yogurt Drink'],
                ['Salted Lassi', 699, 'Traditional Indian Yogurt Drink'],
                ['Mango Lassi', 699, 'Traditional Indian Yogurt Drink'],
                ['Coffee', 499, null],
                ['Soft Drinks', 300, null],
            ],
        ],
        'Desserts' => [
            null,
            [
                ['Gulab Jamun (2 pcs)', 699, 'Fried balls of a dough made from milk solids dipped in sugar syrup'],
                ['Kheer', 699, 'Traditional rice pudding with a hint of cardamom'],
            ],
        ],
    ];

    /** Item name => option groups (see MenuItem::syncOptionGroups()). */
    public const OPTIONS = [
        'Parantha Combo (Chaas / Lassi)' => [
            ['name' => 'Drink', 'min_choices' => 1, 'max_choices' => 1, 'options' => [['name' => 'Chaas', 'price_cents' => 0], ['name' => 'Lassi', 'price_cents' => 0]]],
        ],
        'Desi Patty (Aloo / Paneer / Mix)' => [
            ['name' => 'Filling', 'min_choices' => 1, 'max_choices' => 1, 'options' => [['name' => 'Aloo', 'price_cents' => 0], ['name' => 'Paneer', 'price_cents' => 0], ['name' => 'Mix', 'price_cents' => 0]]],
        ],
        'Black Daal & Rice' => [self::LARGE_COMBO],
        'Rajma & Rice' => [self::LARGE_COMBO],
        'Channe & Rice' => [self::LARGE_COMBO],
    ];

    private const LARGE_COMBO = ['name' => 'Size', 'min_choices' => 0, 'max_choices' => 1, 'options' => [['name' => 'Large Combo', 'price_cents' => 299]]];

    public function run(): void
    {
        if (MenuCategory::exists()) {
            $this->command?->info('The menu already has categories; skipping.');

            return;
        }

        $position = 0;

        foreach (static::MENU as $name => [$description, $items]) {
            $category = MenuCategory::create([
                'name' => $name,
                'description' => $description,
                'sort_order' => $position++,
            ]);

            foreach ($items as $index => [$itemName, $price, $itemDescription]) {
                $item = $category->items()->create([
                    'name' => $itemName,
                    'price_cents' => $price,
                    'description' => $itemDescription,
                    'sort_order' => $index,
                ]);

                $item->syncOptionGroups(static::OPTIONS[$itemName] ?? []);
            }
        }
    }
}
