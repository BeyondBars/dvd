<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A section of the menu (Breakfast, Lunch, Beverages, ...). Hidden categories are left off the website.
 */
class MenuCategory extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'sort_order', 'is_visible'];

    protected $attributes = [
        'sort_order' => 0,
        'is_visible' => true,
    ];

    protected function casts(): array
    {
        return [
            'sort_order' => 'integer',
            'is_visible' => 'boolean',
        ];
    }

    public function items(): HasMany
    {
        return $this->hasMany(MenuItem::class)->orderBy('sort_order')->orderBy('id');
    }

    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('sort_order')->orderBy('id');
    }
}
