<?php

namespace App\Repositories\Eloquent;

use App\Models\User;
use App\Repositories\Contract\IUserRepository;

class UserRepository extends BaseRepository implements IUserRepository
{
    public function __construct(User $model)
    {
        parent::__construct($model);
    }

    public function findByEmail(string $email): \Illuminate\Database\Eloquent\Model
    {
        return $this->findFirstBy('email', $email);
    }

    public function existsByEmail(string $email): bool
    {
        return $this->existsBy('email', $email);
    }

    public function findWithWallet(int $userId): User
    {
        return $this->model->with('wallet')->findOrFail($userId);
    }

    public function findUsersWithWallets(array $userIds): \Illuminate\Database\Eloquent\Collection
    {
        return $this->model->with('wallet')->whereIn('id', $userIds)->get();
    }
}
