<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class MenuOption extends Model
{
    protected $fillable = ['menu_option_group_id', 'name', 'price_cents', 'sort_order'];

    protected $attributes = [
        'price_cents' => 0,
        'sort_order' => 0,
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'sort_order' => 'integer',
        ];
    }

    public function group(): BelongsTo
    {
        return $this->belongsTo(MenuOptionGroup::class, 'menu_option_group_id');
    }
}
