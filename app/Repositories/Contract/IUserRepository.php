<?php

namespace App\Repositories\Contract;

use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface IUserRepository extends IBaseRepository
{
    public function findByEmail(string $email): Model;

    public function existsByEmail(string $email): bool;

    public function findWithWallet(int $userId): User;

    public function findUsersWithWallets(array $userIds): Collection;
}
