<?php

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;
use Illuminate\Support\Facades\DB;

describe('Concurrent Transfer Operations', function () {

    beforeEach(function () {
        $this->sender = User::factory()->withEmail('sender@example.com')->create();
        $this->recipient = User::factory()->withEmail('recipient@example.com')->create();

        $this->senderWallet = Wallet::factory()->forUser($this->sender)->withBalance(1000.00)->create();
        $this->recipientWallet = Wallet::factory()->forUser($this->recipient)->withBalance(500.00)->create();
    });

    describe('Race Condition Prevention', function () {

        it('prevents double-spending with concurrent transfers using database transactions', function () {
            // Set balance that can only handle limited transfers
            $this->senderWallet->update(['balance' => 100.00]);

            $results = [];
            $processes = [];

            // Create multiple transfer requests that would exceed balance if processed concurrently
            for ($i = 0; $i < 5; $i++) {
                $transferData = [
                    'recipient_email' => 'recipient@example.com',
                    'amount' => 25.00, // No fees, total = 25.00 each
                    'idempotency_key' => fake()->uuid,
                ];

                // Execute transfers using separate processes to simulate real concurrency
                $processes[] = function () use ($transferData) {
                    return $this->actingAs($this->sender, 'sanctum')
                        ->postJson('/api/wallet/transfer', $transferData);
                };
            }

            // Execute all processes
            foreach ($processes as $process) {
                $results[] = $process();
            }

            // Analyze results
            $successful = 0;
            $failed = 0;

            foreach ($results as $response) {
                if ($response->status() === 200) {
                    $successful++;
                } elseif ($response->status() === 400 || $response->status() === 409) {
                    $failed++;
                }
            }

            // Only 4 transfers of $25 should succeed with $100 balance
            expect($successful)->toBeLessThanOrEqual(4);
            expect($failed)->toBeGreaterThan(0);

            // Verify final balance is mathematically correct
            $this->senderWallet->refresh();
            $expectedBalance = 100.00 - ($successful * 25.00);
            expect((float)$this->senderWallet->balance)->toBe($expectedBalance);

            // Verify transaction count matches successful transfers
            $transactionCount = Transaction::where('from_wallet_id', $this->senderWallet->id)
                ->where('type', 'transfer')
                ->where('status', 'completed')
                ->count();

            expect($transactionCount)->toBe($successful);
        });

        it('handles rapid sequential transfers correctly', function () {
            $initialBalance = 500.00;
            $this->senderWallet->update(['balance' => $initialBalance]);

            $transferAmount = 20.00;
            $successfulTransfers = 0;

            for ($i = 0; $i < 20; $i++) {
                $response = $this->actingAs($this->sender, 'sanctum')
                    ->postJson('/api/wallet/transfer', [
                        'recipient_email' => 'recipient@example.com',
                        'amount' => $transferAmount,
                        'idempotency_key' => fake()->uuid,
                    ]);

                if ($response->status() === 200) {
                    $successfulTransfers++;
                } else {
                    break;
                }
            }

            $this->senderWallet->refresh();
            $expectedBalance = $initialBalance - ($successfulTransfers * $transferAmount);
            expect((float)$this->senderWallet->balance)->toBe($expectedBalance);
        });

        it('maintains balance consistency with mixed concurrent operations', function () {
            // Create multiple users for realistic testing
            $users = User::factory()->count(3)->create();
            $wallets = [];

            foreach ($users as $user) {
                $wallets[] = Wallet::factory()->forUser($user)->withBalance(200.00)->create();
            }

            $totalInitialBalance = 200.00 * 3; // $600 total system balance

            $results = [];
            $processes = [];

            // Create various concurrent operations
            for ($i = 0; $i < 10; $i++) {
                $senderIndex = $i % 3;
                $recipientIndex = ($i + 1) % 3;

                if ($senderIndex === $recipientIndex) continue;

                $transferData = [
                    'recipient_email' => $users[$recipientIndex]->email,
                    'amount' => 15.00, // Small amount to avoid fees
                    'idempotency_key' => fake()->uuid,
                ];

                $processes[] = function () use ($users, $senderIndex, $transferData) {
                    return $this->actingAs($users[$senderIndex], 'sanctum')
                        ->postJson('/api/wallet/transfer', $transferData);
                };
            }

            // Execute all processes
            foreach ($processes as $process) {
                $results[] = $process();
            }

            // Calculate final total balance
            $totalFinalBalance = 0;
            foreach ($wallets as $wallet) {
                $wallet->refresh();
                $totalFinalBalance += $wallet->balance;
            }

            // Total system balance should remain unchanged (no fees for $15 transfers)
            expect($totalFinalBalance)->toBe($totalInitialBalance);
        });
    });

    describe('Database Locking Verification', function () {

        it('ensures wallet balance updates are atomic', function () {
            $this->senderWallet->update(['balance' => 60.00]);

            $processes = [];

            // Two transfers that together would exceed balance
            $transferData1 = [
                'recipient_email' => 'recipient@example.com',
                'amount' => 35.00,
                'idempotency_key' => fake()->uuid,
            ];

            $transferData2 = [
                'recipient_email' => 'recipient@example.com',
                'amount' => 30.00,
                'idempotency_key' => fake()->uuid,
            ];

            $processes[] = function () use ($transferData1) {
                return $this->actingAs($this->sender, 'sanctum')
                    ->postJson('/api/wallet/transfer', $transferData1);
            };

            $processes[] = function () use ($transferData2) {
                return $this->actingAs($this->sender, 'sanctum')
                    ->postJson('/api/wallet/transfer', $transferData2);
            };

            $results = [];
            foreach ($processes as $process) {
                $results[] = $process();
            }

            // Only one transfer should succeed
            $successCount = 0;
            $failCount = 0;

            foreach ($results as $response) {
                if ($response->status() === 200) {
                    $successCount++;
                } elseif ($response->status() === 400) {
                    $failCount++;
                }
            }

            expect($successCount)->toBe(1)
                ->and($failCount)->toBe(1);
        });

        it('handles concurrent transfers to same recipient correctly', function () {
            // Multiple senders transferring to same recipient
            $sender1 = User::factory()->create();
            $sender2 = User::factory()->create();

            $wallet1 = Wallet::factory()->forUser($sender1)->withBalance(100.00)->create();
            $wallet2 = Wallet::factory()->forUser($sender2)->withBalance(100.00)->create();

            $initialRecipientBalance = $this->recipientWallet->balance;

            $processes = [
                function () use ($sender1) {
                    return $this->actingAs($sender1, 'sanctum')
                        ->postJson('/api/wallet/transfer', [
                            'recipient_email' => 'recipient@example.com',
                            'amount' => 50.00,
                            'idempotency_key' => fake()->uuid,
                        ]);
                },
                function () use ($sender2) {
                    return $this->actingAs($sender2, 'sanctum')
                        ->postJson('/api/wallet/transfer', [
                            'recipient_email' => 'recipient@example.com',
                            'amount' => 75.00,
                            'idempotency_key' => fake()->uuid,
                        ]);
                }
            ];

            $results = [];
            foreach ($processes as $process) {
                $results[] = $process();
            }

            foreach ($results as $response) {
                expect($response->status())->toBe(200);
            }

            $this->recipientWallet->refresh();
            $expectedRecipientBalance = $initialRecipientBalance + 50.00 + 75.00;
            expect((float)$this->recipientWallet->balance)->toBe($expectedRecipientBalance);

            $wallet1->refresh();
            $wallet2->refresh();
            expect((float)$wallet1->balance)->toBe(42.50)
                ->and((float)$wallet2->balance)->toBe(15.00);
        });
    });

    describe('Stress Testing', function () {

        it('handles high volume of small concurrent transfers', function () {
            $this->senderWallet->update(['balance' => 1000.00]);

            $transferCount = 50;
            $transferAmount = 10.00;
            $processes = [];

            for ($i = 0; $i < $transferCount; $i++) {
                $processes[] = function () use ($transferAmount) {
                    return $this->actingAs($this->sender, 'sanctum')
                        ->postJson('/api/wallet/transfer', [
                            'recipient_email' => 'recipient@example.com',
                            'amount' => $transferAmount,
                            'idempotency_key' => fake()->uuid,
                        ]);
                };
            }

            $results = [];
            $successful = 0;

            $batches = array_chunk($processes, 10);

            foreach ($batches as $batch) {
                foreach ($batch as $process) {
                    $response = $process();
                    $results[] = $response;

                    if ($response->status() === 200) {
                        $successful++;
                    }
                }
            }

            $this->senderWallet->refresh();
            $expectedBalance = 1000.00 - ($successful * $transferAmount);
            expect((float)$this->senderWallet->balance)->toBe($expectedBalance);
        });

        it('maintains data integrity under concurrent load with fees', function () {
            $this->senderWallet->update(['balance' => 2000.00]);

            $processes = [];

            $transferAmounts = [20.00, 30.00, 50.00, 100.00];

            for ($i = 0; $i < 20; $i++) {
                $amount = $transferAmounts[$i % 4];

                $processes[] = function () use ($amount) {
                    return $this->actingAs($this->sender, 'sanctum')
                        ->postJson('/api/wallet/transfer', [
                            'recipient_email' => 'recipient@example.com',
                            'amount' => $amount,
                            'idempotency_key' => fake()->uuid,
                        ]);
                };
            }

            $results = [];
            foreach ($processes as $process) {
                $results[] = $process();
            }

            $totalDeducted = 0;
            $successfulCount = 0;

            foreach ($results as $response) {
                if ($response->status() === 200) {
                    $data = $response->json('data');
                    $totalDeducted += $data['transfer_details']['total_deducted'];
                    $successfulCount++;
                }
            }

            // Verify balance consistency
            $this->senderWallet->refresh();
            $expectedBalance = 2000.00 - $totalDeducted;
            expect((float)$this->senderWallet->balance)->toBe($expectedBalance);

            $transactionSum = Transaction::where('from_wallet_id', $this->senderWallet->id)
                ->where('type', 'transfer')
                ->where('status', 'completed')
                ->sum(DB::raw('amount + fee_amount'));

            expect($transactionSum)->toBe($totalDeducted);
        });
    });

    describe('Error Recovery and Consistency', function () {

        it('maintains consistency if partial transfer fails', function () {
            $this->senderWallet->update(['balance' => 50.00]);

            $transferData = [
                'recipient_email' => 'recipient@example.com',
                'amount' => 45.00,
                'idempotency_key' => fake()->uuid,
            ];

            $response = $this->actingAs($this->sender, 'sanctum')
                ->postJson('/api/wallet/transfer', $transferData);

            $response->assertStatus(400);

            $this->senderWallet->refresh();
            $this->recipientWallet->refresh();

            expect((float)$this->senderWallet->balance)->toBe(50.00)
                ->and((float)$this->recipientWallet->balance)->toBe(500.00);

            $this->assertDatabaseMissing('transactions', [
                'idempotency_key' => $transferData['idempotency_key']
            ]);
        });

        it('handles concurrent transfers with same idempotency key gracefully', function () {
            $idempotencyKey = fake()->uuid;

            $processes = [];

            for ($i = 0; $i < 2; $i++) {
                $processes[] = function () use ($idempotencyKey) {
                    return $this->actingAs($this->sender, 'sanctum')
                        ->postJson('/api/wallet/transfer', [
                            'recipient_email' => 'recipient@example.com',
                            'amount' => 25.00,
                            'idempotency_key' => $idempotencyKey,
                        ]);
                };
            }

            $results = [];
            foreach ($processes as $process) {
                $results[] = $process();
            }

            $successCount = 0;
            $duplicateCount = 0;

            foreach ($results as $response) {
                if ($response->status() === 200) {
                    $successCount++;
                } elseif ($response->status() === 409) {
                    $duplicateCount++;
                }
            }

            expect($successCount)->toBe(1)
                ->and($duplicateCount)->toBe(1);

            $transactionCount = Transaction::where('idempotency_key', $idempotencyKey)->count();
            expect($transactionCount)->toBe(1);
        });
    });
});
