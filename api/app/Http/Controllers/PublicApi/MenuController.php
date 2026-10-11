<?php

namespace App\Http\Controllers\PublicApi;

use App\Http\Controllers\Controller;
use App\Http\Resources\MenuCategoryResource;
use App\Models\MenuCategory;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * The menu as the customer website shows it: visible categories only. Sold out items are still
 * listed (with is_available false) so the website can show them as unavailable.
 */
class MenuController extends Controller
{
    public function __invoke(): AnonymousResourceCollection
    {
        return MenuCategoryResource::collection(
            MenuCategory::ordered()->where('is_visible', true)->with('items.optionGroups.options')->get()
        );
    }
}
