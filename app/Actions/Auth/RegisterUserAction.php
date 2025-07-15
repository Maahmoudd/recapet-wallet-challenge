<?php

namespace App\Actions\Auth;

use App\Events\UserCreatedEvent;
use App\Models\User;
use App\Repositories\Contract\IActivityLogRepository;
use App\Repositories\Contract\IUserRepository;
use Illuminate\Support\Facades\DB;

class RegisterUserAction
{
    public function __construct(
        private readonly IUserRepository $userRepository,
        private readonly IActivityLogRepository $activityLogRepository
    ) {}

    public function execute(array $data): array
    {
        $this->activityLogRepository->log('user_registration_attempt', new User(), [
            'metadata' => ['email' => $data['email']]
        ], request());

        return DB::transaction(function () use ($data) {
            $user = $this->userRepository->create($data);

            UserCreatedEvent::dispatch($user, request());

            $token = $user->createToken('auth-token')->plainTextToken;

            $this->activityLogRepository->log('user_registration_success', $user, [
                'metadata' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'wallet_id' => $user->wallet->id
                ]
            ], request(), $user);

            $this->activityLogRepository->log('wallet_created', $user->wallet, [
                'metadata' => ['initial_balance' => '0.00']
            ], request(), $user);

            return [
                'user' => $user->load('wallet'),
                'token' => $token,
                'wallet' => $user->wallet,
            ];
        });
    }
}
