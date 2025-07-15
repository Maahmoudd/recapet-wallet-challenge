<?php

namespace Database\Factories;

use App\Models\Transaction;
use App\Models\Wallet;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $amount = fake()->randomFloat(2, 1, 1000);
        $type = fake()->randomElement(['deposit', 'withdrawal', 'transfer']);

        return [
            'idempotency_key' => 'test_' . Str::random(16) . '_' . time(),
            'from_wallet_id' => $type === 'deposit' ? null : Wallet::factory(),
            'to_wallet_id' => $type === 'withdrawal' ? null : Wallet::factory(),
            'type' => $type,
            'amount' => $amount,
            'fee_amount' => $type === 'transfer' && $amount > 25 ? round(2.50 + ($amount * 0.10), 2) : 0.00,
            'status' => fake()->randomElement(['pending', 'completed', 'failed']),
            'metadata' => [
                'description' => fake()->sentence(),
                'created_by' => 'test_factory',
            ],
        ];
    }

    /**
     * Create a deposit transaction.
     */
    public function deposit(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'deposit',
            'from_wallet_id' => null,
            'fee_amount' => 0.00,
        ]);
    }

    /**
     * Create a withdrawal transaction.
     */
    public function withdrawal(): static
    {
        return $this->state(fn (array $attributes) => [
            'type' => 'withdrawal',
            'to_wallet_id' => null,
            'fee_amount' => 0.00,
        ]);
    }

    /**
     * Create a transfer transaction.
     */
    public function transfer(): static
    {
        $amount = $this->faker->randomFloat(2, 1, 1000);
        $fee = $amount > 25 ? round(2.50 + ($amount * 0.10), 2) : 0.00;

        return $this->state(fn (array $attributes) => [
            'type' => 'transfer',
            'amount' => $amount,
            'fee_amount' => $fee,
        ]);
    }

    /**
     * Create a completed transaction.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
        ]);
    }

    /**
     * Create a pending transaction.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
        ]);
    }

    /**
     * Create a failed transaction.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
        ]);
    }

    /**
     * Create a transaction with specific amount.
     */
    public function withAmount(float $amount): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => $amount,
            'fee_amount' => $attributes['type'] === 'transfer' && $amount > 25
                ? round(2.50 + ($amount * 0.10), 2)
                : 0.00,
        ]);
    }

    /**
     * Create a transaction with specific idempotency key.
     */
    public function withIdempotencyKey(string $key): static
    {
        return $this->state(fn (array $attributes) => [
            'idempotency_key' => $key,
        ]);
    }

    /**
     * Create a transaction between specific wallets.
     */
    public function betweenWallets(Wallet $fromWallet, Wallet $toWallet): static
    {
        return $this->state(fn (array $attributes) => [
            'from_wallet_id' => $fromWallet->id,
            'to_wallet_id' => $toWallet->id,
            'type' => 'transfer',
        ]);
    }

    /**
     * Create a high-value transfer (over $25 to test fees).
     */
    public function highValue(): static
    {
        $amount = fake()->randomFloat(2, 100, 1000);
        return $this->state(fn (array $attributes) => [
            'type' => 'transfer',
            'amount' => $amount,
            'fee_amount' => round(2.50 + ($amount * 0.10), 2),
        ]);
    }

    /**
     * Create a low-value transfer (under $25 to test no fees).
     */
    public function lowValue(): static
    {
        $amount = fake()->randomFloat(2, 1, 25);
        return $this->state(fn (array $attributes) => [
            'type' => 'transfer',
            'amount' => $amount,
            'fee_amount' => 0.00,
        ]);
    }

    /**
     * Create a transaction with specific metadata.
     */
    public function withMetadata(array $metadata): static
    {
        return $this->state(fn (array $attributes) => [
            'metadata' => array_merge($attributes['metadata'] ?? [], $metadata),
        ]);
    }
}
