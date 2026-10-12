<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ProfileRequest;
use App\Http\Resources\UserResource;
use App\Support\ApiResponse;
use Illuminate\Http\JsonResponse;

class ProfileController extends Controller
{
    public function update(ProfileRequest $request): JsonResponse
    {
        $user = $request->user();
        $data = $request->safe()->only(['name', 'email', 'password']);
        if (empty($data['password'])) {
            unset($data['password']);
        } else {
            // log out other devices, keep this one
            $user->tokens()->where('id', '!=', $user->currentAccessToken()->id)->delete();
        }
        if (isset($data['email'])) {
            $data['email'] = strtolower($data['email']);
        }
        $user->update($data);

        return ApiResponse::success(new UserResource($user), 'Profile updated successfully');
    }
}
