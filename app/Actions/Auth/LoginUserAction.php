<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Repositories\Contract\IActivityLogRepository;
use Illuminate\Support\Facades\Auth;

class LoginUserAction
{
    public function __construct(
        private readonly IActivityLogRepository $activityLogRepository
    ) {}

    public function execute(array $credentials): ?array
    {
        $this->activityLogRepository->log('user_login_attempt', new User(), [
            'metadata' => ['email' => $credentials['email']]
        ], request());

        if (!Auth::attempt($credentials)) {
            $this->activityLogRepository->log('user_login_failed', new User(), [
                'metadata' => [
                    'email' => $credentials['email'],
                    'reason' => 'invalid_credentials'
                ]
            ], request());

            return null;
        }

        $user = Auth::user();
        $user->tokens()->delete();
        $token = $user->createToken('auth-token')->plainTextToken;

        $this->activityLogRepository->log('user_login_success', $user, [
            'metadata' => ['email' => $user->email]
        ], request(), $user);

        return [
            'user' => $user->load('wallet'),
            'token' => $token,
        ];
    }
}
