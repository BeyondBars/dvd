<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * App-wide key/value settings (UI theme, app name, ...). Values are stored as JSON.
 *
 * Every key needs a default in DEFAULTS; only keys listed there are ever read or written.
 */
class Setting extends Model
{
    public const DEFAULTS = [
        // Desi Videshi Diner's website colours: saffron, ink and cream.
        'app_name' => 'Desi Videshi Diner',
        'primary_color' => '#f87417',
        'sidebar_background' => '#15110d',
        'sidebar_text' => '#fbf7e9',
        'page_background' => '#fbf7e9',
        'font_family' => 'outfit',
        'border_radius' => 'medium',
    ];

    public const FONTS = ['outfit', 'inter', 'system', 'serif', 'mono'];

    public const RADII = ['none', 'small', 'medium', 'large'];

    protected $fillable = ['key', 'value'];

    protected function casts(): array
    {
        return [
            'value' => 'json',
        ];
    }

    /**
     * All settings, with defaults filled in for keys that haven't been saved yet.
     */
    public static function values(): array
    {
        return array_merge(
            static::DEFAULTS,
            static::query()->whereIn('key', array_keys(static::DEFAULTS))->pluck('value', 'key')->all()
        );
    }

    /**
     * Save the given key/value pairs (unknown keys are ignored).
     */
    public static function store(array $values): void
    {
        foreach (array_intersect_key($values, static::DEFAULTS) as $key => $value) {
            static::updateOrCreate(['key' => $key], ['value' => $value]);
        }
    }

    /**
     * Remove every saved value so the defaults apply again.
     */
    public static function reset(): void
    {
        static::query()->whereIn('key', array_keys(static::DEFAULTS))->delete();
    }
}
