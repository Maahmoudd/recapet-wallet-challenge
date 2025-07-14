<?php

namespace App\Providers;

use App\Models\ActivityLog;
use App\Models\BalanceSnapshot;
use App\Models\LedgerEntry;
use App\Models\Transaction;
use App\Models\User;
use App\Models\Wallet;
use App\Repositories\Contract\IActivityLogRepository;
use App\Repositories\Contract\IBalanceSnapshotRepository;
use App\Repositories\Contract\ILedgerEntryRepository;
use App\Repositories\Contract\ITransactionRepository;
use App\Repositories\Contract\IUserRepository;
use App\Repositories\Contract\IWalletRepository;
use App\Repositories\Eloquent\ActivityLogRepository;
use App\Repositories\Eloquent\BalanceSnapshotRepository;
use App\Repositories\Eloquent\LedgerEntryRepository;
use App\Repositories\Eloquent\TransactionRepository;
use App\Repositories\Eloquent\UserRepository;
use App\Repositories\Eloquent\WalletRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(IUserRepository::class, function ($app) {
            return new UserRepository(new User());
        });

        $this->app->bind(IWalletRepository::class, function ($app) {
            return new WalletRepository(new Wallet());
        });

        $this->app->bind(IActivityLogRepository::class, function ($app) {
            return new ActivityLogRepository(new ActivityLog());
        });

        $this->app->bind(ITransactionRepository::class, function ($app) {
            return new TransactionRepository(new Transaction());
        });

        $this->app->bind(ILedgerEntryRepository::class, function ($app) {
            return new LedgerEntryRepository(new LedgerEntry());
        });

        $this->app->bind(IBalanceSnapshotRepository::class, function ($app) {
            return new BalanceSnapshotRepository(new BalanceSnapshot());
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
