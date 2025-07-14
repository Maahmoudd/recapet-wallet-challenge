<?php

namespace App\Repositories\Contract;

use App\Models\ActivityLog;
use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

interface IActivityLogRepository extends IBaseRepository
{

    public function log(string $action, Object $model, array $data = [], $request = null, ?User $user = null): ?ActivityLog;

    public function getActivitiesForUser(User $user, array $filters = []): LengthAwarePaginator;

    public function deleteOldLogs(int $daysToKeep = 365): int;

    public function searchActivities(string $query, User $user, array $filters = []): LengthAwarePaginator;
}
