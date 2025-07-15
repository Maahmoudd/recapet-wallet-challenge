<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class DatabaseSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $this->command->info('🚀 Starting Recapet Wallet System Seeding...');
        $this->command->newLine();

        DB::statement('SET FOREIGN_KEY_CHECKS=0;');

        try {
            $this->command->info('📝 Step 1: Creating users and wallets...');
            $this->call(UserSeeder::class);
            $this->command->newLine();

            $this->command->info('💳 Step 2: Creating transaction history...');
            $this->call(TransactionSeeder::class);
            $this->command->newLine();

        } finally {
            DB::statement('SET FOREIGN_KEY_CHECKS=1;');
        }

        $this->displayFinalSummary();
    }

    private function displayFinalSummary(): void
    {
        $users = \App\Models\User::count();
        $wallets = \App\Models\Wallet::count();
        $transactions = \App\Models\Transaction::count();

        $totalBalance = \App\Models\Wallet::sum('balance');
        $avgBalance = \App\Models\Wallet::avg('balance');

        $this->command->info('🎉 Seeding completed successfully!');
        $this->command->newLine();

        $this->command->info('=== FINAL SUMMARY ===');
        $this->command->table(
            ['Metric', 'Count/Value'],
            [
                ['Users Created', number_format($users)],
                ['Wallets Created', number_format($wallets)],
                ['Transactions Created', number_format($transactions)],
                ['Total System Balance', '$' . number_format($totalBalance, 2)],
                ['Average Wallet Balance', '$' . number_format($avgBalance, 2)],
            ]
        );

        $this->command->newLine();
        $this->command->info('🔑 Demo Login Credentials:');
        $this->command->table(
            ['Email', 'Password', 'Role', 'Balance'],
            [
                ['admin@recapet.com', 'admin123', 'Admin User', '$50,000.00'],
                ['john@example.com', 'password123', 'High Balance User', '$5,000.00'],
                ['jane@example.com', 'password123', 'Regular User', '$2,500.00'],
                ['bob@example.com', 'password123', 'Low Balance User', '$100.00'],
                ['alice@example.com', 'password123', 'Premium User', '$10,000.00'],
            ]
        );

        $this->command->newLine();
        $this->command->info('📚 Next Steps:');
        $this->command->info('1. Start the application: php artisan serve');
        $this->command->info('2. Login with demo credentials above');
        $this->command->info('3. Test transfers, deposits, and withdrawals');
        $this->command->info('4. Run load tests: php artisan wallet:simulate-load');
        $this->command->newLine();
    }
}
