<?php

namespace App\Actions\Auth;

use App\Repositories\Contract\IUserRepository;

class GetUserWithWalletAction
{
    public function __construct(
        private readonly IUserRepository $userRepository
    ) {}
    public function execute(int $userId)
    {
        return $this->userRepository->findWithWallet($userId);
    }
}
