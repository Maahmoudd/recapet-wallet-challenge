<?php

namespace App\Actions\Auth;

use App\Models\User;
use App\Repositories\Contract\IActivityLogRepository;

class LogoutUserAction
{
    public function __construct(
        private readonly IActivityLogRepository $activityLogRepository
    ) {}

    public function execute(User $user): bool
    {
        $result = $user->tokens()->delete();

        $this->activityLogRepository->log('user_logout_success', $user, [
            'metadata' => ['tokens_revoked' => $result]
        ], request(), $user);

        return $result;
    }
}
