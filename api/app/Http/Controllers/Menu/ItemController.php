<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveItemRequest;
use App\Http\Requests\Menu\SortRequest;
use App\Http\Resources\MenuItemResource;
use App\Models\MenuCategory;
use App\Models\MenuItem;
use App\Support\Enums\UserAbility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

class ItemController extends Controller
{
    public function store(SaveItemRequest $request): MenuItemResource
    {
        $data = $request->validated();

        $item = MenuItem::create([
            ...$data,
            // New items go to the end of their category.
            'sort_order' => (MenuItem::where('menu_category_id', $data['menu_category_id'])->max('sort_order') ?? -1) + 1,
        ]);

        return new MenuItemResource($item);
    }

    public function update(SaveItemRequest $request, MenuItem $item): MenuItemResource
    {
        $data = $request->validated();

        // Moving to another category puts the item at the end of that category.
        if (isset($data['menu_category_id']) && (int) $data['menu_category_id'] !== $item->menu_category_id) {
            $data['sort_order'] = (MenuItem::where('menu_category_id', $data['menu_category_id'])->max('sort_order') ?? -1) + 1;
        }

        $item->update($data);

        return new MenuItemResource($item);
    }

    public function destroy(Request $request, MenuItem $item): Response
    {
        abort_unless($request->user()->can(UserAbility::ManageMenu->value), 403);

        $item->delete();

        return response()->noContent();
    }

    /**
     * Reorder the items of one category. Ids from other categories are ignored.
     */
    public function sort(SortRequest $request, MenuCategory $category): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request, $category) {
            foreach ($request->validated('ids') as $position => $id) {
                $category->items()->whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return MenuItemResource::collection($category->items()->get());
    }
}
