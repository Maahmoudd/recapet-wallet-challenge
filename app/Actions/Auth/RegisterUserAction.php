<?php

namespace App\Actions\Auth;

use App\Events\UserCreatedEvent;
use App\Models\User;
use App\Repositories\Contract\IActivityLogRepository;
use App\Repositories\Contract\IUserRepository;
use App\Repositories\Contract\IWalletRepository;
use Illuminate\Support\Facades\DB;

class RegisterUserAction
{
    public function __construct(
        private readonly IUserRepository $userRepository,
        private readonly IWalletRepository $walletRepository,
        private readonly IActivityLogRepository $activityLogRepository
    ) {}

    public function execute(array $data): array
    {
        $this->activityLogRepository->log('user_registration_attempt', new User(), [
            'metadata' => ['email' => $data['email']]
        ], request());

        return DB::transaction(function () use ($data) {
            $user = $this->userRepository->create($data);

            UserCreatedEvent::dispatch($user);
            $wallet = $this->walletRepository->create([
                'user_id' => $user->id,
            ]);

            $token = $user->createToken('auth-token')->plainTextToken;

            $this->activityLogRepository->log('user_registration_success', $user, [
                'metadata' => [
                    'name' => $user->name,
                    'email' => $user->email,
                    'wallet_id' => $wallet->id
                ]
            ], request(), $user);

            $this->activityLogRepository->log('wallet_created', $wallet, [
                'metadata' => ['initial_balance' => '0.00']
            ], request(), $user);

            return [
                'user' => $user->load('wallet'),
                'token' => $token,
                'wallet' => $wallet,
            ];
        });
    }
}
