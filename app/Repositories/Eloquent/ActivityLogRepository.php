<?php

namespace App\Repositories\Eloquent;

use App\Models\ActivityLog;
use App\Models\User;
use App\Repositories\Contract\IActivityLogRepository;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;

class ActivityLogRepository extends BaseRepository implements IActivityLogRepository
{
    public function __construct(ActivityLog $model)
    {
        parent::__construct($model);
    }

    public function log(string $action, Object $model, array $data = [], $request = null, ?User $user = null): ?ActivityLog
    {
        try {
            $user = $user ?: Auth::user();

            if (!$user) {
                return null;
            }

            $logData = [
                'user_id' => $user->id,
                'action' => $action,
                'entity_type' => get_class($model) ?? null,
                'entity_id' => $model->id ?? null,
                'description' => $this->generateActivityDescription($action, $data),
                'metadata' => $data['metadata'] ?? [],
                'changes' => $this->generateChanges($model),
                'ip_address' => $request?->ip(),
                'user_agent' => $request?->userAgent(),
                'api_endpoint' => $request?->getPathInfo(),
                'request_method' => $request?->getMethod(),
            ];

            return ActivityLog::create($logData);
        } catch (\Exception $e) {
            logger()->error('Failed to create activity log', [
                'action' => $action,
                'error' => $e->getMessage(),
                'user_id' => $user?->id
            ]);
            return null;
        }
    }

    protected function generateActivityDescription(string $action, array $data): string
    {
        $templates = $this->getActivityTemplates();
        $template = $templates[$action] ?? $action;

        // Replace placeholders with actual values
        foreach ($data['metadata'] ?? [] as $key => $value) {
            if (is_string($value) || is_numeric($value)) {
                $template = str_replace('{' . $key . '}', $value, $template);
            }
        }

        return $template;
    }

    protected function generateChanges($model): array
    {
        if (!is_object($model) || !($model instanceof Model)) {
            return [];
        }

        $newValues = $this->filterAttributes($model->getAttributes());

        return !empty($newValues) ? ['new' => $newValues] : [];
    }

    protected function filterAttributes(array $attributes): array
    {
        $filtered = $attributes;

        // Remove timestamps
        unset($filtered['created_at'], $filtered['updated_at']);

        // Remove sensitive fields
        $sensitiveFields = ['password', 'password_hash', 'remember_token', 'api_token'];
        foreach ($sensitiveFields as $field) {
            if (isset($filtered[$field])) {
                $filtered[$field] = '[HIDDEN]';
            }
        }

        return $filtered;
    }

    /**
     * Get activities for a specific user with filtering
     */
    public function getActivitiesForUser(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = ActivityLog::with(['user:id,email'])
            ->where('user_id', $user->id);

        $this->applyActivityFilters($query, $filters);

        return $query->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 25, ['*'], '', $filters['page'] ?? 1);
    }

    protected function applyActivityFilters(Builder $query, array $filters): void
    {
        $query
            ->when(!empty($filters['action']), function ($q) use ($filters) {
                $q->where('action', $filters['action']);
            })
            ->when(!empty($filters['entity_type']), function ($q) use ($filters) {
                $q->where('entity_type', $filters['entity_type']);
            })
            ->when(!empty($filters['date_from']), function ($q) use ($filters) {
                $q->whereDate('created_at', '>=', $filters['date_from']);
            })
            ->when(!empty($filters['date_to']), function ($q) use ($filters) {
                $q->whereDate('created_at', '<=', $filters['date_to']);
            })
            ->when(!empty($filters['ip_address']), function ($q) use ($filters) {
                $q->where('ip_address', $filters['ip_address']);
            })
            ->when(!empty($filters['api_endpoint']), function ($q) use ($filters) {
                $q->where('api_endpoint', 'like', '%' . $filters['api_endpoint'] . '%');
            })
            ->when(!empty($filters['request_method']), function ($q) use ($filters) {
                $q->where('request_method', $filters['request_method']);
            });
    }

    protected function getActivityTemplates(): array
    {
        return [
            // Auth actions
            'user_registration_attempt' => 'Registration attempt for email: {email}',
            'user_registration_success' => 'User registered successfully',
            'user_registration_failed' => 'Registration failed for email: {email}',
            'user_login_attempt' => 'Login attempt for email: {email}',
            'user_login_success' => 'User logged in successfully',
            'user_login_failed' => 'Login failed for email: {email}',
            'user_logout_success' => 'User logged out successfully',

            // Wallet actions
            'wallet_created' => 'Wallet created for user',
            'wallet_deposit' => 'Deposited ${amount} to wallet',
            'wallet_withdrawal' => 'Withdrew ${amount} from wallet',
            'wallet_transfer_sent' => 'Sent ${amount} to {recipient_email}',
            'wallet_transfer_received' => 'Received ${amount} from {sender_email}',
            'wallet_fee_charged' => 'Fee of ${fee_amount} charged for transfer',

            // Transaction actions
            'transaction_created' => 'Transaction created: {transaction_type}',
            'transaction_completed' => 'Transaction completed: {transaction_type}',
            'transaction_failed' => 'Transaction failed: {transaction_type}',
            'transaction_pending' => 'Transaction pending: {transaction_type}',

            // Ledger actions
            'ledger_entry_created' => 'Ledger entry created: {entry_type}',
            'balance_updated' => 'Wallet balance updated to ${new_balance}',

            // System actions
            'idempotency_check' => 'Idempotency check for key: {idempotency_key}',
            'duplicate_request_blocked' => 'Duplicate request blocked: {idempotency_key}',
        ];
    }

    public function deleteOldLogs(int $daysToKeep = 365): int
    {
        return ActivityLog::where('created_at', '<', now()->subDays($daysToKeep))
            ->delete();
    }

    public function searchActivities(string $query, User $user, array $filters = []): LengthAwarePaginator
    {
        $builder = ActivityLog::with(['user:id,email'])
            ->where('user_id', $user->id)
            ->where(function ($q) use ($query) {
                $q->where('description', 'like', '%' . $query . '%')
                    ->orWhereJsonContains('metadata', $query);
            });

        $this->applyActivityFilters($builder, $filters);

        return $builder->orderBy('created_at', 'desc')
            ->paginate($filters['per_page'] ?? 25);
    }
}
