<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

class MenuItem extends Model
{
    use HasFactory;

    protected $fillable = ['menu_category_id', 'name', 'description', 'price_cents', 'sort_order', 'is_available'];

    protected $attributes = [
        'sort_order' => 0,
        'is_available' => true,
    ];

    protected function casts(): array
    {
        return [
            'price_cents' => 'integer',
            'sort_order' => 'integer',
            'is_available' => 'boolean',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(MenuCategory::class, 'menu_category_id');
    }

    public function optionGroups(): HasMany
    {
        return $this->hasMany(MenuOptionGroup::class)->orderBy('sort_order')->orderBy('id');
    }

    /**
     * Replace the item's option groups with the given ones:
     * [['name' => 'Size', 'min_choices' => 0, 'max_choices' => 1, 'options' => [['name' => 'Large', 'price_cents' => 299]]]]
     * Past orders keep their own copy of the options, so nothing is lost.
     */
    public function syncOptionGroups(array $groups): void
    {
        DB::transaction(function () use ($groups) {
            $this->optionGroups()->delete();

            foreach (array_values($groups) as $position => $group) {
                $created = $this->optionGroups()->create([
                    'name' => $group['name'],
                    'min_choices' => $group['min_choices'] ?? 0,
                    'max_choices' => $group['max_choices'] ?? 1,
                    'sort_order' => $position,
                ]);

                foreach (array_values($group['options'] ?? []) as $index => $option) {
                    $created->options()->create([
                        'name' => $option['name'],
                        'price_cents' => $option['price_cents'] ?? 0,
                        'sort_order' => $index,
                    ]);
                }
            }
        });

        $this->unsetRelation('optionGroups');
    }
}
