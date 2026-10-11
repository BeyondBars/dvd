<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A choice for a menu item: min_choices 1 / max_choices 1 is "pick one" (required), min 0 is optional.
 */
class MenuOptionGroup extends Model
{
    protected $fillable = ['menu_item_id', 'name', 'min_choices', 'max_choices', 'sort_order'];

    protected $attributes = [
        'min_choices' => 0,
        'max_choices' => 1,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'min_choices' => 'integer',
            'max_choices' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function item(): BelongsTo
    {
        return $this->belongsTo(MenuItem::class, 'menu_item_id');
    }

    public function options(): HasMany
    {
        return $this->hasMany(MenuOption::class)->orderBy('sort_order')->orderBy('id');
    }
}
