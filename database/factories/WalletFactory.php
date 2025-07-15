<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Wallet>
 */
class WalletFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'balance' => fake()->randomFloat(2, 0, 10000),
            'status' => 'active',
        ];
    }

    /**
     * Create a wallet for a specific user.
     */
    public function forUser(User $user): static
    {
        return $this->state(fn (array $attributes) => [
            'user_id' => $user->id,
        ]);
    }

    /**
     * Create a wallet with a specific balance.
     */
    public function withBalance(float $balance): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => $balance,
        ]);
    }

    /**
     * Create an inactive wallet.
     */
    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'closed',
        ]);
    }

    /**
     * Create an active wallet.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'active',
        ]);
    }

    /**
     * Create a wallet with zero balance for testing.
     */
    public function empty(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 0.00,
        ]);
    }

    /**
     * Create a wallet with sufficient balance for testing.
     */
    public function withSufficientBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 1000.00,
        ]);
    }

    /**
     * Create a wallet with insufficient balance for testing.
     */
    public function withInsufficientBalance(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 10.00,
        ]);
    }

    /**
     * Create a wallet for transfer testing scenarios.
     */
    public function forTransferTesting(): static
    {
        return $this->state(fn (array $attributes) => [
            'balance' => 500.00,
            'status' => 'active',
        ]);
    }
}
