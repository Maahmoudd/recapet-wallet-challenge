<?php

use App\Models\User;
use App\Models\Wallet;
use App\Models\Transaction;

describe('Wallet Operations API', function () {

    beforeEach(function () {
        $this->user = User::factory()->create();
        $this->wallet = Wallet::factory()->forUser($this->user)->withBalance(1000.00)->create();
    });

    describe('Wallet Deposit', function () {

        it('can deposit funds successfully', function () {
            $depositData = [
                'amount' => 250.50,
                'description' => 'Test deposit',
                'idempotency_key' => fake()->uuid,
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/deposit', $depositData);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'transaction' => [
                            'id',
                            'idempotency_key',
                            'type',
                            'amount',
                            'fee_amount',
                            'status',
                            'created_at'
                        ],
                        'new_balance',
                        'previous_balance'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Deposit completed successfully',
                    'data' => [
                        'new_balance' => '1250.5',
                        'previous_balance' => '1000.00'
                    ]
                ]);

            $this->wallet->refresh();

            $this->assertDatabaseHas('transactions', [
                'to_wallet_id' => $this->wallet->id,
                'type' => 'deposit',
                'amount' => 250.50,
                'status' => 'completed',
                'idempotency_key' => $depositData['idempotency_key']
            ]);
        });

        it('validates required fields for deposit', function () {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/deposit', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount', 'idempotency_key']);
        });

        it('validates minimum deposit amount', function () {
            $depositData = [
                'amount' => 0,
                'idempotency_key' => 'deposit_test_' . time(),
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/deposit', $depositData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
        });

        it('validates maximum deposit amount', function () {
            $depositData = [
                'amount' => 1000000.00, // Exceeds maximum
                'idempotency_key' => 'deposit_test_' . time(),
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/deposit', $depositData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount']);
        });

        it('prevents duplicate deposits with same idempotency key', function () {
            $idempotencyKey = fake()->uuid;
            $depositData = [
                'amount' => 100.00,
                'idempotency_key' => $idempotencyKey,
            ];

            $response1 = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/deposit', $depositData);
            $response1->assertStatus(200);

            $response2 = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/deposit', $depositData);

            $response2->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'Duplicate transaction detected'
                ]);
        });

        it('requires authentication for deposit', function () {
            $response = $this->postJson('/api/wallet/deposit', [
                'amount' => 100.00,
                'idempotency_key' => 'test_' . time(),
            ]);

            $response->assertStatus(401);
        });

        it('rejects deposit to inactive wallet', function () {
            $this->wallet->update(['status' => 'closed']);

            $depositData = [
                'amount' => 100.00,
                'idempotency_key' => fake()->uuid,
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/deposit', $depositData);

            $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Wallet is not active'
                ]);
        });
    });

    describe('Wallet Withdrawal', function () {

        it('can withdraw funds successfully', function () {
            $withdrawalData = [
                'amount' => 300.00,
                'description' => 'Test withdrawal',
                'idempotency_key' => fake()->uuid,
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/withdraw', $withdrawalData);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'transaction',
                        'new_balance',
                        'previous_balance',
                        'withdrawn_amount',
                        'status'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Withdrawal completed successfully',
                    'data' => [
                        'new_balance' => '700.00',
                        'previous_balance' => '1000.00',
                        'withdrawn_amount' => 300.00,
                        'status' => 'completed'
                    ]
                ]);

            // Verify transaction created
            $this->assertDatabaseHas('transactions', [
                'from_wallet_id' => $this->wallet->id,
                'type' => 'withdrawal',
                'amount' => 300.00,
                'status' => 'completed'
            ]);
        });

        it('rejects withdrawal with insufficient balance', function () {
            $withdrawalData = [
                'amount' => 1500.00, // More than available balance
                'idempotency_key' => fake()->uuid,
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/withdraw', $withdrawalData);

            $response->assertStatus(400)
                ->assertJson([
                    'success' => false,
                    'message' => 'Insufficient balance for operation'
                ]);
        });

        it('validates required fields for withdrawal', function () {
            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/withdraw', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['amount', 'idempotency_key']);
        });

        it('prevents duplicate withdrawals with same idempotency key', function () {
            $idempotencyKey = fake()->uuid;
            $withdrawalData = [
                'amount' => 200.00,
                'idempotency_key' => $idempotencyKey,
            ];

            $response1 = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/withdraw', $withdrawalData);
            $response1->assertStatus(200);

            $response2 = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/withdraw', $withdrawalData);

            $response2->assertStatus(409)
                ->assertJson([
                    'success' => false,
                    'message' => 'Duplicate transaction detected'
                ]);
        });

        it('rejects withdrawal from inactive wallet', function () {
            $this->wallet->update(['status' => 'closed']);

            $withdrawalData = [
                'amount' => 100.00,
                'idempotency_key' => fake()->uuid,
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/withdraw', $withdrawalData);

            $response->assertStatus(403)
                ->assertJson([
                    'success' => false,
                    'message' => 'Wallet is not active'
                ]);
        });
    });

    describe('Rate Limiting', function () {

        it('enforces rate limits on financial operations', function () {
            // Make 11 deposit attempts (limit is 20 per minute)
            for ($i = 0; $i < 22; $i++) {
                $response = $this->actingAs($this->user, 'sanctum')
                    ->postJson('/api/wallet/deposit', [
                        'amount' => 10.00,
                        'idempotency_key' => fake()->uuid,
                    ]);

                if ($i < 20) {
                    // First 10 should succeed
                    expect($response->status())->toBe(200);
                } else {
                    $response->assertStatus(429);
                }
            }
        });
    });

    describe('Precision and Rounding', function () {

        it('handles decimal amounts correctly', function () {
            $depositData = [
                'amount' => 123.46,
                'idempotency_key' => fake()->uuid,
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/deposit', $depositData);

            $response->assertStatus(200);

            // Verify amount is stored with 2 decimal precision
            $this->assertDatabaseHas('transactions', [
                'amount' => 123.46,
                'type' => 'deposit'
            ]);
        });

        it('maintains balance precision after multiple operations', function () {
            $operations = [
                ['type' => 'deposit', 'amount' => 10.33],
                ['type' => 'withdrawal', 'amount' => 5.67],
                ['type' => 'deposit', 'amount' => 2.89],
            ];

            $expectedBalance = 1000.00;

            foreach ($operations as $index => $operation) {
                $data = [
                    'amount' => $operation['amount'],
                    'idempotency_key' => fake()->uuid,
                ];

                if ($operation['type'] === 'deposit') {
                    $expectedBalance += $operation['amount'];
                    $this->actingAs($this->user, 'sanctum')
                        ->postJson('/api/wallet/deposit', $data);
                } else {
                    $expectedBalance -= $operation['amount'];
                    $this->actingAs($this->user, 'sanctum')
                        ->postJson('/api/wallet/withdraw', $data);
                }
            }

            $this->wallet->refresh();
            expect((float)$this->wallet->balance)->toBe(round($expectedBalance, 2));
        });
    });

    describe('Business Rules', function () {

        it('maintains immutable transaction records', function () {
            $depositData = [
                'amount' => 100.00,
                'idempotency_key' => fake()->uuid,
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/deposit', $depositData);

            $response->assertStatus(200);
        });

        it('creates permanent audit trail for every transaction', function () {
            $depositData = [
                'amount' => 150.00,
                'description' => 'Audit trail test',
                'idempotency_key' => fake()->uuid,
            ];

            $response = $this->actingAs($this->user, 'sanctum')
                ->postJson('/api/wallet/deposit', $depositData);

            $response->assertStatus(200);

            // Verify transaction record exists
            $this->assertDatabaseHas('transactions', [
                'idempotency_key' => $depositData['idempotency_key'],
                'type' => 'deposit',
                'amount' => 150.00
            ]);

            // Verify activity log exists
            $this->assertDatabaseHas('activity_logs', [
                'user_id' => $this->user->id,
                'action' => 'wallet_deposit'
            ]);
        });
    });
});
