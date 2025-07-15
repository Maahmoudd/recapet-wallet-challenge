<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Wallet;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating users and wallets...');

        $this->createDemoUsers();

        $this->createRegularUsers();

        $this->createScenarioUsers();

        $this->command->info('Users and wallets created successfully!');
    }

    private function createDemoUsers(): void
    {
        $demoUsers = [
            [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'password123',
                'balance' => 5000.00,
                'role' => 'High Balance User'
            ],
            [
                'name' => 'Jane Smith',
                'email' => 'jane@example.com',
                'password' => 'password123',
                'balance' => 2500.00,
                'role' => 'Regular User'
            ],
            [
                'name' => 'Bob Wilson',
                'email' => 'bob@example.com',
                'password' => 'password123',
                'balance' => 100.00,
                'role' => 'Low Balance User'
            ],
            [
                'name' => 'Alice Johnson',
                'email' => 'alice@example.com',
                'password' => 'password123',
                'balance' => 10000.00,
                'role' => 'Premium User'
            ],
            [
                'name' => 'Demo Admin',
                'email' => 'admin@recapet.com',
                'password' => 'admin123',
                'balance' => 50000.00,
                'role' => 'Admin User'
            ]
        ];

        foreach ($demoUsers as $userData) {
            $user = User::create([
                'name' => $userData['name'],
                'email' => $userData['email'],
                'email_verified_at' => now(),
                'password' => Hash::make($userData['password']),
            ]);

            Wallet::create([
                'user_id' => $user->id,
                'balance' => $userData['balance'],
                'status' => 'active',
            ]);

            $this->command->info("Created {$userData['role']}: {$userData['email']} with \${$userData['balance']} balance");
        }
    }

    private function createRegularUsers(): void
    {
        $this->command->info('Creating 20 regular users...');

        // Create 20 regular users with random but realistic data
        for ($i = 1; $i <= 20; $i++) {
            $user = User::factory()->create([
                'email_verified_at' => now(),
            ]);

            // Create realistic balance distributions
            $balance = $this->generateRealisticBalance();

            Wallet::create([
                'user_id' => $user->id,
                'balance' => $balance,
                'status' => 'active',
            ]);
        }

        $this->command->info('Regular users created successfully!');
    }

    private function createScenarioUsers(): void
    {
        $scenarios = [
            ['name' => 'Empty Wallet User', 'balance' => 0.00, 'count' => 3],
            ['name' => 'Small Balance User', 'balance' => 25.00, 'count' => 5],
            ['name' => 'Medium Balance User', 'balance' => 500.00, 'count' => 5],
            ['name' => 'High Balance User', 'balance' => 2000.00, 'count' => 3],
            ['name' => 'Inactive Wallet User', 'balance' => 1000.00, 'count' => 2, 'inactive' => true],
        ];

        foreach ($scenarios as $scenario) {
            $this->command->info("Creating {$scenario['count']} {$scenario['name']}(s)...");

            for ($i = 1; $i <= $scenario['count']; $i++) {
                $user = User::factory()->create([
                    'email_verified_at' => now(),
                ]);

                Wallet::create([
                    'user_id' => $user->id,
                    'balance' => $scenario['balance'],
                    'status' => !isset($scenario['inactive']) ? 'active' : 'closed',
                ]);
            }
        }
    }

    private function generateRealisticBalance(): float
    {
        $rand = rand(1, 100);

        if ($rand <= 20) {
            return fake()->randomFloat(2, 0, 100);
        } elseif ($rand <= 60) {
            return fake()->randomFloat(2, 100, 1000);
        } elseif ($rand <= 90) {
            return fake()->randomFloat(2, 1000, 5000);
        } else {
            return fake()->randomFloat(2, 5000, 20000);
        }
    }
}
