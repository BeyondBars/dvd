<?php

namespace App\Support\Enums;

enum UserAbility: string
{
    case ReadUsers = 'read users';
    case WriteUsers = 'write users';
    case ReadSettings = 'read settings';
    case ManageSettings = 'manage settings';
    case ReadMenu = 'read menu';
    case ManageMenu = 'manage menu';
    case ReadOrders = 'read orders';
    case ManageOrders = 'manage orders';
    case ManageEmailTemplates = 'manage email templates';

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
            'menu' => [
                'read' => ['name' => self::ReadMenu, 'excludes' => []],
                'manage' => ['name' => self::ManageMenu, 'excludes' => []],
            ],
            'orders' => [
                'read' => ['name' => self::ReadOrders, 'excludes' => []],
                'manage' => ['name' => self::ManageOrders, 'excludes' => []],
            ],
            'email templates' => [
                'manage' => ['name' => self::ManageEmailTemplates, 'excludes' => []],
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
                'description' => 'App appearance, online ordering hours and the roles list.',
                'view' => self::ReadSettings,
            ],
            'menu' => [
                'title' => 'Menu',
                'description' => 'The food menu shown and ordered from on the website.',
                'view' => self::ReadMenu,
            ],
            'orders' => [
                'title' => 'Orders',
                'description' => 'Online orders from the website.',
                'view' => self::ReadOrders,
            ],
        ];
    }

    public function label(): string
    {
        return match ($this) {
            self::ReadUsers, self::ReadSettings, self::ReadMenu, self::ReadOrders => 'View',
            self::WriteUsers, self::ManageSettings, self::ManageMenu, self::ManageOrders => 'Manage',
        };
    }

    public function description(): string
    {
        return match ($this) {
            self::ReadUsers => 'See the users list and open user details.',
            self::WriteUsers => 'Invite, edit, disable and delete users.',
            self::ReadSettings => 'Open the settings page.',
            self::ManageSettings => 'Change the app appearance.',
            self::ReadMenu => 'See the menu.',
            self::ManageMenu => 'Add, edit and remove menu items, change prices and mark items sold out.',
            self::ReadOrders => 'See online orders.',
            self::ManageOrders => 'Accept, update and cancel orders and mark them paid.',
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
            self::ManageMenu => self::ReadMenu,
            self::ManageOrders => self::ReadOrders,
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
