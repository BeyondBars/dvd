<?php

namespace Tests\Feature;

use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\Enums\UserAbility;
use Database\Seeders\MenuSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MenuTest extends TestCase
{
    use RefreshDatabase;

    public function testAdminSeesEveryCategoryAndItemInOrder()
    {
        $second = MenuCategory::factory()->create(['name' => 'Lunch', 'sort_order' => 1]);
        $first = MenuCategory::factory()->hidden()->create(['name' => 'Breakfast', 'sort_order' => 0]);
        MenuItem::factory()->for($first, 'category')->create(['name' => 'B', 'sort_order' => 1]);
        MenuItem::factory()->for($first, 'category')->soldOut()->create(['name' => 'A', 'sort_order' => 0]);

        $this->beAdmin()->getJson('menu/categories')
            ->assertSuccessful()
            ->assertJsonPath('data.0.name', 'Breakfast')
            ->assertJsonPath('data.0.is_visible', false)
            ->assertJsonPath('data.0.items.0.name', 'A')
            ->assertJsonPath('data.0.items.0.is_available', false)
            ->assertJsonPath('data.0.items.1.name', 'B')
            ->assertJsonPath('data.1.id', $second->id);
    }

    public function testViewingTheMenuNeedsTheReadAbility()
    {
        $this->getJson('menu/categories')->assertUnauthorized();
        $this->beUser()->getJson('menu/categories')->assertForbidden();
        $this->beUserAbleTo(UserAbility::ReadMenu->value)->getJson('menu/categories')->assertSuccessful();
    }

    public function testChangingTheMenuNeedsTheManageAbility()
    {
        $category = MenuCategory::factory()->create();
        $item = MenuItem::factory()->for($category, 'category')->create();

        $this->beUserAbleTo(UserAbility::ReadMenu->value);

        $this->postJson('menu/categories', ['name' => 'New'])->assertForbidden();
        $this->putJson("menu/categories/{$category->id}", ['name' => 'New'])->assertForbidden();
        $this->deleteJson("menu/categories/{$category->id}")->assertForbidden();
        $this->putJson('menu/categories/sort', ['ids' => [$category->id]])->assertForbidden();
        $this->postJson('menu/items', ['menu_category_id' => $category->id, 'name' => 'X', 'price_cents' => 100])->assertForbidden();
        $this->putJson("menu/items/{$item->id}", ['is_available' => false])->assertForbidden();
        $this->deleteJson("menu/items/{$item->id}")->assertForbidden();
        $this->putJson("menu/categories/{$category->id}/items/sort", ['ids' => [$item->id]])->assertForbidden();

        $this->assertTrue($item->fresh()->is_available);
    }

    public function testCategoriesCanBeCreatedUpdatedAndDeleted()
    {
        MenuCategory::factory()->create(['sort_order' => 4]);
        $this->beUserAbleTo(UserAbility::ManageMenu->value);

        $id = $this->postJson('menu/categories', ['name' => 'Specials', 'description' => 'This week only'])
            ->assertSuccessful()
            ->assertJsonPath('data.sort_order', 5)
            ->assertJsonPath('data.is_visible', true)
            ->json('data.id');

        $this->putJson("menu/categories/{$id}", ['is_visible' => false])
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'Specials')
            ->assertJsonPath('data.is_visible', false);

        MenuItem::factory()->create(['menu_category_id' => $id]);
        $this->deleteJson("menu/categories/{$id}")->assertNoContent();

        $this->assertDatabaseMissing('menu_categories', ['id' => $id]);
        $this->assertDatabaseMissing('menu_items', ['menu_category_id' => $id]);
    }

    public function testCategoriesAreValidated()
    {
        $this->beAdmin()->postJson('menu/categories', ['name' => ''])->assertJsonValidationErrors('name');
        $this->beAdmin()->postJson('menu/categories', ['name' => str_repeat('a', 101)])->assertJsonValidationErrors('name');
    }

    public function testItemsCanBeCreatedUpdatedAndDeleted()
    {
        $category = MenuCategory::factory()->create();
        MenuItem::factory()->for($category, 'category')->create(['sort_order' => 2]);
        $this->beAdmin();

        $id = $this->postJson('menu/items', [
            'menu_category_id' => $category->id,
            'name' => 'Masala Fries',
            'price_cents' => 799,
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.price_cents', 799)
            ->assertJsonPath('data.sort_order', 3)
            ->assertJsonPath('data.is_available', true)
            ->json('data.id');

        $this->putJson("menu/items/{$id}", ['is_available' => false, 'price_cents' => 899])
            ->assertSuccessful()
            ->assertJsonPath('data.name', 'Masala Fries')
            ->assertJsonPath('data.price_cents', 899)
            ->assertJsonPath('data.is_available', false);

        $this->deleteJson("menu/items/{$id}")->assertNoContent();
        $this->assertDatabaseMissing('menu_items', ['id' => $id]);
    }

    public function testMovingAnItemPutsItAtTheEndOfTheNewCategory()
    {
        $item = MenuItem::factory()->create(['sort_order' => 0]);
        $other = MenuCategory::factory()->create();
        MenuItem::factory()->for($other, 'category')->create(['sort_order' => 6]);

        $this->beAdmin()->putJson("menu/items/{$item->id}", ['menu_category_id' => $other->id])
            ->assertSuccessful()
            ->assertJsonPath('data.menu_category_id', $other->id)
            ->assertJsonPath('data.sort_order', 7);
    }

    public function testItemsAreValidated()
    {
        $this->beAdmin()->postJson('menu/items', [
            'menu_category_id' => 999,
            'name' => '',
            'price_cents' => -1,
        ])->assertJsonValidationErrors(['menu_category_id', 'name', 'price_cents']);

        $this->beAdmin()->postJson('menu/items', [])->assertJsonValidationErrors(['menu_category_id', 'name', 'price_cents']);
    }

    public function testCategoriesAndItemsCanBeReordered()
    {
        [$a, $b, $c] = MenuCategory::factory()->count(3)->sequence(fn ($s) => ['sort_order' => $s->index])->create();

        $this->beAdmin()->putJson('menu/categories/sort', ['ids' => [$c->id, $a->id, $b->id]])
            ->assertSuccessful()
            ->assertJsonPath('data.0.id', $c->id)
            ->assertJsonPath('data.1.id', $a->id)
            ->assertJsonPath('data.2.id', $b->id);

        [$x, $y] = MenuItem::factory()->for($a, 'category')->count(2)->sequence(fn ($s) => ['sort_order' => $s->index])->create();
        $elsewhere = MenuItem::factory()->for($b, 'category')->create(['sort_order' => 9]);

        $this->putJson("menu/categories/{$a->id}/items/sort", ['ids' => [$y->id, $elsewhere->id, $x->id]])
            ->assertSuccessful()
            ->assertJsonPath('data.0.id', $y->id)
            ->assertJsonPath('data.1.id', $x->id);

        // Items of other categories are left alone.
        $this->assertSame(9, $elsewhere->fresh()->sort_order);
    }

    public function testTheWebsiteSeesVisibleCategoriesWithoutLoggingIn()
    {
        $visible = MenuCategory::factory()->create();
        MenuCategory::factory()->hidden()->create();
        MenuItem::factory()->for($visible, 'category')->soldOut()->create(['name' => 'Kheer', 'price_cents' => 699]);

        $this->getJson('public/menu')
            ->assertSuccessful()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.id', $visible->id)
            ->assertJsonPath('data.0.items.0.name', 'Kheer')
            ->assertJsonPath('data.0.items.0.price_cents', 699)
            ->assertJsonPath('data.0.items.0.is_available', false);
    }

    public function testTheMenuSeederOnlyFillsAnEmptyMenu()
    {
        $this->seed(MenuSeeder::class);

        $this->assertSame(count(MenuSeeder::MENU), MenuCategory::count());
        $this->assertSame(1299, MenuItem::where('name', 'Aloo Pyaaj Paranthas')->value('price_cents'));
        $patty = MenuItem::where('name', 'Desi Patty (Aloo / Paneer / Mix)')->firstOrFail();
        $this->assertSame(['Aloo', 'Paneer', 'Mix'], $patty->optionGroups->first()->options->pluck('name')->all());

        $items = MenuItem::count();
        $this->seed(MenuSeeder::class);

        $this->assertSame($items, MenuItem::count());
    }

    public function testItemsCanHaveChoicesWhichAreReplacedAsAWhole()
    {
        $category = MenuCategory::factory()->create();
        $this->beAdmin();

        $id = $this->postJson('menu/items', [
            'menu_category_id' => $category->id,
            'name' => 'Desi Patty',
            'price_cents' => 599,
            'option_groups' => [
                ['name' => 'Filling', 'min_choices' => 1, 'max_choices' => 1, 'options' => [
                    ['name' => 'Aloo', 'price_cents' => 0],
                    ['name' => 'Paneer', 'price_cents' => 100],
                ]],
            ],
        ])
            ->assertSuccessful()
            ->assertJsonPath('data.option_groups.0.name', 'Filling')
            ->assertJsonPath('data.option_groups.0.min_choices', 1)
            ->assertJsonPath('data.option_groups.0.options.1.name', 'Paneer')
            ->assertJsonPath('data.option_groups.0.options.1.price_cents', 100)
            ->json('data.id');

        // Updating other fields leaves the choices alone.
        $this->putJson("menu/items/{$id}", ['price_cents' => 649])->assertJsonCount(1, 'data.option_groups');

        $this->putJson("menu/items/{$id}", ['option_groups' => [
            ['name' => 'Extras', 'min_choices' => 0, 'max_choices' => 2, 'options' => [['name' => 'Chutney', 'price_cents' => 50]]],
        ]])
            ->assertJsonCount(1, 'data.option_groups')
            ->assertJsonPath('data.option_groups.0.name', 'Extras');

        $this->assertDatabaseCount('menu_option_groups', 1);
        $this->assertDatabaseCount('menu_options', 1);

        $this->getJson('public/menu')->assertJsonPath('data.0.items.0.option_groups.0.options.0.name', 'Chutney');

        $this->putJson("menu/items/{$id}", ['option_groups' => []])->assertJsonCount(0, 'data.option_groups');
    }

    public function testChoicesAreValidated()
    {
        $item = MenuItem::factory()->create();

        $this->beAdmin()->putJson("menu/items/{$item->id}", ['option_groups' => [
            ['name' => '', 'min_choices' => 2, 'max_choices' => 1, 'options' => [['name' => 'A', 'price_cents' => -1]]],
            ['name' => 'Empty', 'min_choices' => 0, 'max_choices' => 1, 'options' => []],
        ]])->assertJsonValidationErrors([
            'option_groups.0.name',
            'option_groups.0.max_choices',
            'option_groups.0.min_choices',
            'option_groups.0.options.0.price_cents',
            'option_groups.1.options',
        ]);
    }
}
