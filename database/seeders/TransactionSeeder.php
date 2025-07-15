<?php

namespace Database\Seeders;

use App\Models\User;
use App\Models\Transaction;
use Illuminate\Database\Seeder;

class TransactionSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $this->command->info('Creating realistic transaction history...');

        $users = User::whereHas('wallet')->with('wallet')->get();

        if ($users->count() < 2) {
            $this->command->error('Need at least 2 users to create transactions. Run UserSeeder first.');
            return;
        }

        $this->createDepositTransactions($users);
        $this->createWithdrawalTransactions($users);
        $this->createTransferTransactions($users);
        $this->createHistoricalTransactions($users);

        $this->command->info('Transaction history created successfully!');
        $this->displayTransactionSummary();
    }

    private function createDepositTransactions($users): void
    {
        $this->command->info('Creating deposit transactions...');

        foreach ($users as $user) {
            $depositCount = rand(2, 8);

            for ($i = 0; $i < $depositCount; $i++) {
                $amount = $this->generateRealisticDepositAmount();

                Transaction::create([
                    'idempotency_key' => fake()->uuid,
                    'from_wallet_id' => null,
                    'to_wallet_id' => $user->wallet->id,
                    'type' => 'deposit',
                    'amount' => $amount,
                    'fee_amount' => 0.00,
                    'status' => $this->getRandomStatus(['completed' => 95, 'pending' => 3, 'failed' => 2]),
                    'metadata' => [
                        'description' => fake()->randomElement([
                            'Bank transfer deposit',
                            'Credit card deposit',
                            'Salary deposit',
                            'Freelance payment',
                            'Investment return',
                            'Gift money',
                            'Bonus payment'
                        ]),
                        'source' => fake()->randomElement(['bank_transfer', 'credit_card', 'paypal', 'stripe']),
                    ],
                    'created_at' => $this->generateRandomDate(),
                ]);
            }
        }
    }

    private function createWithdrawalTransactions($users): void
    {
        $this->command->info('Creating withdrawal transactions...');

        foreach ($users as $user) {
            if ($user->wallet->balance > 100) {
                $withdrawalCount = rand(1, 4);

                for ($i = 0; $i < $withdrawalCount; $i++) {
                    $maxAmount = min($user->wallet->balance * 0.3, 500);
                    $amount = fake()->randomFloat(2, 10, $maxAmount);

                    Transaction::create([
                        'idempotency_key' => fake()->uuid,
                        'from_wallet_id' => $user->wallet->id,
                        'to_wallet_id' => null,
                        'type' => 'withdrawal',
                        'amount' => $amount,
                        'fee_amount' => 0.00,
                        'status' => $this->getRandomStatus(['completed' => 90, 'pending' => 5, 'failed' => 5]),
                        'metadata' => [
                            'description' => fake()->randomElement([
                                'ATM withdrawal',
                                'Bank transfer',
                                'Cash withdrawal',
                                'Bill payment',
                                'Emergency withdrawal'
                            ]),
                            'destination' => fake()->randomElement(['bank_account', 'paypal', 'stripe']),
                        ],
                        'created_at' => $this->generateRandomDate(),
                    ]);
                }
            }
        }
    }

    private function createTransferTransactions($users): void
    {
        $this->command->info('Creating transfer transactions...');

        $transferCount = 50;

        for ($i = 0; $i < $transferCount; $i++) {
            $sender = $users->random();
            $recipient = $users->where('id', '!=', $sender->id)->random();

            $amount = $this->generateRealisticTransferAmount();
            $fee = $this->calculateFee($amount);

            if ($sender->wallet->balance >= ($amount + $fee)) {
                Transaction::create([
                    'idempotency_key' => fake()->uuid,
                    'from_wallet_id' => $sender->wallet->id,
                    'to_wallet_id' => $recipient->wallet->id,
                    'type' => 'transfer',
                    'amount' => $amount,
                    'fee_amount' => $fee,
                    'status' => $this->getRandomStatus(['completed' => 92, 'pending' => 5, 'failed' => 3]),
                    'metadata' => [
                        'transfer_type' => 'p2p',
                        'sender_id' => $sender->id,
                        'sender_email' => $sender->email,
                        'recipient_id' => $recipient->id,
                        'recipient_email' => $recipient->email,
                        'description' => fake()->randomElement([
                            'Payment for services',
                            'Split bill',
                            'Loan repayment',
                            'Gift',
                            'Shared expense',
                            'Freelance payment',
                            'Rent payment',
                            'Dinner split',
                            'Emergency help'
                        ]),
                        'fee_calculation' => [
                            'base_fee' => $fee > 0 ? 2.50 : 0,
                            'percentage_fee' => $fee > 0 ? ($amount * 0.10) : 0,
                            'total_fee' => $fee,
                            'fee_applied' => $fee > 0,
                        ]
                    ],
                    'created_at' => $this->generateRandomDate(),
                ]);
            }
        }
    }

    private function createHistoricalTransactions($users): void
    {
        $this->command->info('Creating historical transactions for balance snapshots...');

        foreach ($users->take(10) as $user) {
            $historicalCount = rand(5, 15);

            for ($i = 0; $i < $historicalCount; $i++) {
                $type = fake()->randomElement(['deposit', 'withdrawal', 'transfer']);
                $amount = fake()->randomFloat(2, 5, 200);

                if ($type === 'transfer') {
                    $recipient = $users->where('id', '!=', $user->id)->random();
                    $fee = $this->calculateFee($amount);

                    Transaction::create([
                        'idempotency_key' => fake()->uuid,
                        'from_wallet_id' => $user->wallet->id,
                        'to_wallet_id' => $recipient->wallet->id,
                        'type' => 'transfer',
                        'amount' => $amount,
                        'fee_amount' => $fee,
                        'status' => 'completed',
                        'metadata' => [
                            'transfer_type' => 'p2p',
                            'sender_email' => $user->email,
                            'recipient_email' => $recipient->email,
                            'description' => 'Historical transfer',
                        ],
                        'created_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
                    ]);
                } else {
                    Transaction::create([
                        'idempotency_key' => fake()->uuid,
                        'from_wallet_id' => $type === 'withdrawal' ? $user->wallet->id : null,
                        'to_wallet_id' => $type === 'deposit' ? $user->wallet->id : null,
                        'type' => $type,
                        'amount' => $amount,
                        'fee_amount' => 0.00,
                        'status' => 'completed',
                        'metadata' => [
                            'description' => "Historical {$type}",
                        ],
                        'created_at' => fake()->dateTimeBetween('-6 months', '-1 month'),
                    ]);
                }
            }
        }
    }

    private function generateRealisticDepositAmount(): float
    {
        $rand = rand(1, 100);

        if ($rand <= 30) {
            return fake()->randomFloat(2, 10, 100);
        } elseif ($rand <= 60) {
            return fake()->randomFloat(2, 100, 500);
        } elseif ($rand <= 85) {
            return fake()->randomFloat(2, 500, 2000);
        } else {
            return fake()->randomFloat(2, 2000, 10000);
        }
    }

    private function generateRealisticTransferAmount(): float
    {
        $rand = rand(1, 100);

        if ($rand <= 40) {
            return fake()->randomFloat(2, 5, 50);
        } elseif ($rand <= 70) {
            return fake()->randomFloat(2, 50, 200);
        } elseif ($rand <= 90) {
            return fake()->randomFloat(2, 200, 1000);
        } else {
            return fake()->randomFloat(2, 1000, 5000);
        }
    }

    private function calculateFee(float $amount): float
    {
        if ($amount <= 25.00) {
            return 0;
        }

        $baseFee = 2.50;
        $percentageFee = $amount * 0.10;

        return round($baseFee + $percentageFee, 2);
    }

    private function generateRandomDate(): \DateTime
    {
        return fake()->dateTimeBetween('-3 months', 'now');
    }

    private function getRandomStatus(array $weights): string
    {
        $random = rand(1, 100);
        $current = 0;

        foreach ($weights as $status => $weight) {
            $current += $weight;
            if ($random <= $current) {
                return $status;
            }
        }

        return 'completed';
    }

    private function displayTransactionSummary(): void
    {
        $total = Transaction::count();
        $deposits = Transaction::where('type', 'deposit')->count();
        $withdrawals = Transaction::where('type', 'withdrawal')->count();
        $transfers = Transaction::where('type', 'transfer')->count();

        $this->command->info("\n=== Transaction Summary ===");
        $this->command->info("Total Transactions: {$total}");
        $this->command->info("Deposits: {$deposits}");
        $this->command->info("Withdrawals: {$withdrawals}");
        $this->command->info("Transfers: {$transfers}");
        $this->command->info("=========================\n");
    }
}
