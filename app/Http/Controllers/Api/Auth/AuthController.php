<?php

namespace App\Http\Controllers\Api\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $user = User::query()->where('email', strtolower($request->string('email')))->first();

        if (! $user || ! Hash::check($request->string('password'), $user->password)) {
            return ApiResponse::error('These credentials do not match our records.', 401);
        }
        if (! $user->is_active) {
            return ApiResponse::error('This account has been disabled.', 403);
        }

        $user->forceFill(['last_login_at' => now()])->saveQuietly();

        $expiresAt = now()->addMinutes((int) (config('sanctum.expiration') ?: 60 * 24 * 7));
        $token = $user->createToken($request->input('device_name', 'admin-panel'), ['*'], $expiresAt);

        return ApiResponse::success([
            'token' => $token->plainTextToken,
            'token_type' => 'Bearer',
            'expires_at' => $expiresAt->toIso8601String(),
            'user' => (new UserResource($user))->resolve($request),
        ], 'Logged in successfully');
    }

    public function logout(Request $request): JsonResponse
    {
        $request->user()?->currentAccessToken()?->delete();

        return ApiResponse::success(null, 'Logged out successfully');
    }

    public function me(Request $request): JsonResponse
    {
        return ApiResponse::success(new UserResource($request->user()), 'Current user');
    }
}
