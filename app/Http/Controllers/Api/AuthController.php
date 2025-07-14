<?php

namespace App\Http\Controllers\Api;

use App\Actions\Auth\GetUserWithWalletAction;
use App\Actions\Auth\LoginUserAction;
use App\Actions\Auth\LogoutUserAction;
use App\Actions\Auth\RegisterUserAction;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Requests\Auth\RegisterRequest;
use App\Http\Resources\UserResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends BaseApiController
{
    public function register(RegisterRequest $request, RegisterUserAction $registerUserAction): JsonResponse
    {
        return rescue(function () use ($request, $registerUserAction) {
            $result = $registerUserAction->execute($request->validated());

            return $this->createdResponse([
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ], 'User registered successfully');
        }, fn(\Throwable $e) => $this->errorResponse('Registration failed'));
    }

    public function login(LoginRequest $request, LoginUserAction $loginUserAction): JsonResponse
    {
        return rescue(function () use ($request, $loginUserAction) {
            $result = $loginUserAction->execute($request->validated());

            if (!$result) {
                return $this->unauthorizedResponse('Invalid credentials');
            }

            return $this->successResponse([
                'user' => new UserResource($result['user']),
                'token' => $result['token'],
            ], 'Login successful');
        }, fn(\Throwable $e) => $this->errorResponse('Login failed'));
    }

    public function logout(Request $request, LogoutUserAction $logoutUserAction): JsonResponse
    {
        return rescue(function () use ($request, $logoutUserAction) {
            $user = Auth::user();
            $logoutUserAction->execute($user);
            return $this->successResponse(null, 'Logged out successfully');
        }, fn() => $this->errorResponse('Logout failed'));
    }

    public function me(Request $request, GetUserWithWalletAction $getUserWithWalletAction): JsonResponse
    {
        return rescue(function () use ($getUserWithWalletAction){
            $user = Auth::user();
            $userWithWallet = $getUserWithWalletAction->execute($user->id);

            return $this->successResponse([
                'user' => new UserResource($userWithWallet),
            ], 'User retrieved successfully');
        }, fn() => $this->errorResponse('Failed to retrieve user data'));
    }
}
