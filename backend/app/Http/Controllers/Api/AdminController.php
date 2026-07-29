<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\JsonResponse;

class AdminController extends Controller
{
    /**
     * POST /api/v1/admin/update-role
     * Super Admin only: change a user's role to 'user' or 'admin'.
     *
     * Business rules enforced here (beyond FormRequest):
     *  1. Super Admin cannot change their own role.
     *  2. Super Admin accounts cannot be downgraded via API.
     */
    public function updateRole(UpdateUserRoleRequest $request): JsonResponse
    {
        $actor  = $request->user();
        $target = User::findOrFail($request->validated('user_id'));

        // Rule 1: Cannot modify self
        if ($actor->id === $target->id) {
            return response()->json([
                'message' => 'Anda tidak dapat mengubah role akun Anda sendiri.',
            ], 403);
        }

        // Rule 2: Cannot downgrade another super_admin
        if ($target->role === 'super_admin') {
            return response()->json([
                'message' => 'Akun Super Admin tidak dapat diubah rolenya via API.',
            ], 403);
        }

        $target->update(['role' => $request->validated('role')]);

        return response()->json([
            'message' => "Role user #{$target->id} berhasil diubah menjadi '{$target->role}'.",
            'data'    => [
                'id'   => $target->id,
                'name' => $target->name,
                'role' => $target->role,
            ],
        ]);
    }

    /**
     * GET /api/v1/admin/users
     * Super Admin/Admin: list all users.
     */
    public function users(): JsonResponse
    {
        $users = User::orderBy('created_at', 'desc')->get();

        $mapped = $users->map(function ($u) {
            return [
                'id'         => (string) $u->id,
                'full_name'  => $u->name,
                'email'      => $u->email,
                'role'       => $u->role,
                'created_at' => $u->created_at?->toIso8601String(),
                'updated_at' => $u->updated_at?->toIso8601String(),
            ];
        });

        return response()->json(['data' => $mapped]);
    }
}

