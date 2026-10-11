<?php

namespace App\Http\Controllers\Menu;

use App\Http\Controllers\Controller;
use App\Http\Requests\Menu\SaveCategoryRequest;
use App\Http\Requests\Menu\SortRequest;
use App\Http\Resources\MenuCategoryResource;
use App\Models\MenuCategory;
use App\Support\Enums\UserAbility;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\DB;

/**
 * The whole menu for the admin: every category (hidden ones too) with every item.
 */
class CategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        abort_unless($request->user()->can(UserAbility::ReadMenu->value), 403);

        return MenuCategoryResource::collection(MenuCategory::ordered()->with('items.optionGroups.options')->get());
    }

    public function store(SaveCategoryRequest $request): MenuCategoryResource
    {
        $category = MenuCategory::create([
            ...$request->validated(),
            // New categories go to the end.
            'sort_order' => (MenuCategory::max('sort_order') ?? -1) + 1,
        ]);

        return new MenuCategoryResource($category->load('items.optionGroups.options'));
    }

    public function update(SaveCategoryRequest $request, MenuCategory $category): MenuCategoryResource
    {
        $category->update($request->validated());

        return new MenuCategoryResource($category->load('items.optionGroups.options'));
    }

    /**
     * Deletes the category and its items.
     */
    public function destroy(Request $request, MenuCategory $category): Response
    {
        abort_unless($request->user()->can(UserAbility::ManageMenu->value), 403);

        $category->delete();

        return response()->noContent();
    }

    public function sort(SortRequest $request): AnonymousResourceCollection
    {
        DB::transaction(function () use ($request) {
            foreach ($request->validated('ids') as $position => $id) {
                MenuCategory::whereKey($id)->update(['sort_order' => $position]);
            }
        });

        return $this->index($request);
    }
}
