<?php

namespace App\Support\Enums;

enum UserRole: string
{
    case SuperAdmin = 'super_admin';
    case User = 'user';
    case Staff = 'staff';

    public function title(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Super Admin',
            self::User => 'User',
            self::Staff => 'Staff',
        };
    }

    public function desc(): string
    {
        return match ($this) {
            self::SuperAdmin => 'Has full access to all system features, settings, and user management.',
            self::User => 'Standard user with access to general features and personal profile management.',
            self::Staff => 'Internal team member with access to operational tools and limited administrative privileges.',
        };
    }

    /**
     * Abilities granted to the role by `php artisan abilities:refresh`.
     */
    public function defaultPermissions(): array
    {
        return match ($this) {
            self::SuperAdmin => UserAbility::forRole($this),
            // Staff run the truck day to day: they handle orders, mark items sold out and change the menu.
            self::Staff => [
                UserAbility::ReadUsers->value,
                UserAbility::ReadSettings->value,
                UserAbility::ReadMenu->value,
                UserAbility::ManageMenu->value,
                UserAbility::ReadOrders->value,
                UserAbility::ManageOrders->value,
            ],
            self::User => [],
        };
    }
}
