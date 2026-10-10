<?php

namespace App\Services;

use App\Models\User;
use App\Support\Enums\UserAbility;
use Silber\Bouncer\BouncerFacade as Bouncer;

/**
 * Per-user permissions on top of the user's role.
 *
 * The role gives the defaults. A user can be given an ability their role lacks (Bouncer "allow")
 * or have one their role grants taken away (Bouncer "forbid"). Setting an ability back to what
 * the role gives removes the override.
 */
class UserPermissions
{
    /**
     * Every module with its abilities and, for the given user, where each one stands.
     */
    public function for(User $user): array
    {
        $user->load('role.abilities', 'abilities');

        $fromRole = $user->role?->abilities->pluck('name')->all() ?? [];
        $overrides = $user->abilities->mapWithKeys(
            fn ($ability) => [$ability->name => $ability->pivot->forbidden ? 'forbid' : 'allow']
        );

        return collect(UserAbility::modules())->map(function (array $module, string $key) use ($fromRole, $overrides) {
            $abilities = collect(UserAbility::grouped()[$key])->map(function (array $definition) use ($fromRole, $overrides) {
                /** @var UserAbility $ability */
                $ability = $definition['name'];
                $role = in_array($ability->value, $fromRole, true);
                $override = $overrides->get($ability->value);

                return [
                    'name' => $ability->value,
                    'label' => $ability->label(),
                    'description' => $ability->description(),
                    'requires' => $ability->requires()?->value,
                    'role' => $role,
                    'override' => $override,
                    'granted' => $override ? $override === 'allow' : $role,
                ];
            })->values();

            return [
                'key' => $key,
                'title' => $module['title'],
                'description' => $module['description'],
                'view' => $module['view']->value,
                'abilities' => $abilities,
            ];
        })->values()->all();
    }

    /**
     * Set which abilities the user should end up with ([ability name => bool]). Abilities left out
     * keep their current state; granting an ability also grants the one it requires.
     */
    public function sync(User $user, array $permissions): void
    {
        $user->load('role.abilities', 'abilities');

        foreach ($permissions as $name => $granted) {
            if ($granted && ($required = UserAbility::from($name)->requires())) {
                $permissions[$required->value] = true;
            }
        }

        $fromRole = $user->role?->abilities->pluck('name')->all() ?? [];

        foreach ($permissions as $name => $granted) {
            // Clear any existing override first, then add one only where it differs from the role.
            $user->disallow($name);
            $user->unforbid($name);

            if ($granted !== in_array($name, $fromRole, true)) {
                $granted ? $user->allow($name) : $user->forbid($name);
            }
        }

        Bouncer::refreshFor($user);
    }

    /**
     * Drop every override so the user has exactly their role's abilities.
     */
    public function reset(User $user): void
    {
        $user->abilities()->detach();

        Bouncer::refreshFor($user);
    }
}
