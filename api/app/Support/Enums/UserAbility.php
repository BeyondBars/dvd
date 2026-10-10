<?php

namespace App\Support\Enums;

enum UserAbility: string
{
    case ReadUsers = 'read users';
    case WriteUsers = 'write users';
    case ReadSettings = 'read settings';
    case ManageSettings = 'manage settings';

    /**
     * Abilities grouped by module for display/syncing. "excludes" lists role values that may never hold it.
     */
    public static function grouped(): array
    {
        return [
            'users' => [
                'read' => ['name' => self::ReadUsers, 'excludes' => []],
                'write' => ['name' => self::WriteUsers, 'excludes' => []],
            ],
            'settings' => [
                'read' => ['name' => self::ReadSettings, 'excludes' => []],
                'manage' => ['name' => self::ManageSettings, 'excludes' => []],
            ],
        ];
    }

    /**
     * Modules shown on the user permissions page. The "view" ability of a module also decides
     * whether its sidebar item is shown.
     */
    public static function modules(): array
    {
        return [
            'users' => [
                'title' => 'User management',
                'description' => 'The users list and user details.',
                'view' => self::ReadUsers,
            ],
            'settings' => [
                'title' => 'Settings',
                'description' => 'App appearance and the roles list.',
                'view' => self::ReadSettings,
            ],
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::ReadUsers, self::ReadSettings => 'View',
            self::WriteUsers, self::ManageSettings => 'Manage',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ReadUsers => 'See the users list and open user details.',
            self::WriteUsers => 'Invite, edit, disable and delete users.',
            self::ReadSettings => 'Open the settings page.',
            self::ManageSettings => 'Change the app appearance.',
        };
    }

    /**
     * An ability that must also be held for this one to make sense (managing implies viewing).
     */
    public function requires(): ?self
    {
        return match ($this) {
            self::WriteUsers => self::ReadUsers,
            self::ManageSettings => self::ReadSettings,
            default => null,
        };
    }

    /**
     * All ability values, minus any excluded for the given role.
     */
    public static function forRole(UserRole $role): array
    {
        $abilities = [];

        foreach (static::grouped() as $group) {
            foreach ($group as $ability) {
                if (!in_array($role->value, $ability['excludes'], true)) {
                    $abilities[] = $ability['name']->value;
                }
            }
        }

        return $abilities;
    }
}
