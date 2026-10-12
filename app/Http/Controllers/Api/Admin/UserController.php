<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UserRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class UserController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = User::query()->withCount('posts');
        if ($role = $request->query('role')) {
            $query->where('role', $role);
        }

        return ApiResponse::success(UserResource::collection($this->paginateQuery($request, $query, ['name', 'email'], ['name', 'email', 'role', 'last_login_at', 'id'], 'name')), 'Users retrieved successfully');
    }

    public function store(UserRequest $request): JsonResponse
    {
        $data = $request->validated();
        $data['email'] = strtolower($data['email']);

        return ApiResponse::created(new UserResource(User::create($data)), 'User created successfully');
    }

    public function show(User $user): JsonResponse
    {
        return ApiResponse::success(new UserResource($user->loadCount('posts')), 'User retrieved successfully');
    }

    public function update(UserRequest $request, User $user): JsonResponse
    {
        $data = $request->validated();
        if (empty($data['password'])) {
            unset($data['password']);
        }
        if (isset($data['email'])) {
            $data['email'] = strtolower($data['email']);
        }
        if ($user->is($request->user()) && (($data['role'] ?? 'admin') !== 'admin' || ($data['is_active'] ?? true) === false)) {
            return ApiResponse::error('You cannot remove your own admin access or disable your own account.', 422);
        }
        $user->update($data);
        if (isset($data['password']) || ($data['is_active'] ?? true) === false) {
            $user->tokens()->delete(); // force re-login everywhere
        }

        return ApiResponse::success(new UserResource($user), 'User updated successfully');
    }

    public function destroy(Request $request, User $user): JsonResponse
    {
        if ($user->is($request->user())) {
            return ApiResponse::error('You cannot delete your own account.', 422);
        }
        if ($user->isAdmin() && User::where('role', 'admin')->count() <= 1) {
            return ApiResponse::error('At least one admin account must remain.', 422);
        }
        $user->tokens()->delete();
        $user->delete();

        return ApiResponse::success(null, 'User deleted successfully');
    }
}
