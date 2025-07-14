<?php

namespace App\Repositories\Contract;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;

interface IBaseRepository
{
    public function all(): Collection;

    public function find(int $id): ?Model;

    public function findOrFail(int $id): Model;

    public function create(array $data): Model;

    public function update(Model $model, array $data): bool;

    public function delete(Model $model): bool;

    public function paginate(int $perPage = 25, array $columns = ['*']): LengthAwarePaginator;

    public function findBy(string $field, mixed $value): Collection;

    public function findFirstBy(string $field, mixed $value): ?Model;

    public function existsBy(string $field, mixed $value): bool;

    public function getModel(): Model;
}
