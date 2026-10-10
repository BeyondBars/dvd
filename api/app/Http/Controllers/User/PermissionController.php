<?php

namespace App\Http\Controllers\User;

use App\Http\Controllers\Controller;
use App\Http\Requests\User\UpdatePermissionsRequest;
use App\Models\User;
use App\Services\UserPermissions;
use Illuminate\Http\JsonResponse;

/**
 * A user's module permissions (role defaults plus per-user overrides). Super admins only.
 */
class PermissionController extends Controller
{
    public function __construct(private UserPermissions $permissions)
    {
    }

    public function show(User $user): JsonResponse
    {
        $this->authorize('managePermissions', $user);

        return $this->respond($user);
    }

    public function update(UpdatePermissionsRequest $request, User $user): JsonResponse
    {
        $this->permissions->sync($user, array_map('boolval', $request->validated('permissions')));

        return $this->respond($user);
    }

    public function destroy(User $user): JsonResponse
    {
        $this->authorize('managePermissions', $user);

        $this->permissions->reset($user);

        return $this->respond($user);
    }

    private function respond(User $user): JsonResponse
    {
        return response()->json(['data' => [
            'role' => $user->role?->title,
            'modules' => $this->permissions->for($user),
        ]]);
    }
}
