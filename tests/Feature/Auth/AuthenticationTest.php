<?php

use App\Models\User;
use App\Models\Wallet;

describe('Authentication API', function () {

    describe('User Registration', function () {

        it('can register a new user successfully', function () {
            $userData = [
                'name' => 'John Doe',
                'email' => 'john@example.com',
                'password' => 'SecurePassword123',
                'password_confirmation' => 'SecurePassword123',
            ];

            $response = $this->postJson('/api/auth/register', $userData);

            $response->assertStatus(201)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'created_at',
                            'wallet' => [
                                'id',
                                'balance',
                                'status',
                                'created_at',
                                'updated_at',
                            ]
                        ],
                        'token'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'User registered successfully'
                ]);

            // Verify user was created in database
            $this->assertDatabaseHas('users', [
                'name' => 'John Doe',
                'email' => 'john@example.com'
            ]);

            $user = User::where('email', 'john@example.com')->first();
            expect($user->wallet)->not->toBeNull();
        });

        it('validates required fields during registration', function () {
            $response = $this->postJson('/api/auth/register', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['name', 'email', 'password']);
        });

        it('validates email format during registration', function () {
            $userData = [
                'name' => 'John Doe',
                'email' => 'invalid-email',
                'password' => 'SecurePassword123',
                'password_confirmation' => 'SecurePassword123',
            ];

            $response = $this->postJson('/api/auth/register', $userData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        });

        it('prevents duplicate email registration', function () {
            $existingUser = User::factory()->withEmail('john@example.com')->create();

            $userData = [
                'name' => 'Another John',
                'email' => 'john@example.com',
                'password' => 'SecurePassword123',
                'password_confirmation' => 'SecurePassword123',
            ];

            $response = $this->postJson('/api/auth/register', $userData);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email']);
        });

        it('ensures one wallet per user during registration', function () {
            $userData = [
                'name' => 'Test User',
                'email' => 'test@example.com',
                'password' => 'SecurePassword123',
                'password_confirmation' => 'SecurePassword123',
            ];

            $response = $this->postJson('/api/auth/register', $userData);
            $response->assertStatus(201);

            $user = User::where('email', 'test@example.com')->first();
            $walletCount = Wallet::where('user_id', $user->id)->count();

            expect($walletCount)->toBe(1);
        });
    });

    describe('User Login', function () {

        beforeEach(function () {
            $this->user = User::factory()
                ->withEmail('test@example.com')
                ->withPassword('password123')
                ->create();

            Wallet::factory()->forUser($this->user)->create();
        });

        it('can login with valid credentials', function () {
            $loginData = [
                'email' => 'test@example.com',
                'password' => 'password123',
            ];

            $response = $this->postJson('/api/auth/login', $loginData);

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email'
                        ],
                        'token'
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'message' => 'Login successful'
                ]);

            $token = $response->json('data.token');
            expect($token)->not->toBeNull();
        });

        it('rejects invalid email during login', function () {
            $loginData = [
                'email' => 'nonexistent@example.com',
                'password' => 'password123',
            ];

            $response = $this->postJson('/api/auth/login', $loginData);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ]);
        });

        it('rejects invalid password during login', function () {
            $loginData = [
                'email' => 'test@example.com',
                'password' => 'wrongpassword',
            ];

            $response = $this->postJson('/api/auth/login', $loginData);

            $response->assertStatus(401)
                ->assertJson([
                    'success' => false,
                    'message' => 'Invalid credentials'
                ]);
        });

        it('validates required fields during login', function () {
            $response = $this->postJson('/api/auth/login', []);

            $response->assertStatus(422)
                ->assertJsonValidationErrors(['email', 'password']);
        });
    });

    describe('User Profile', function () {

        beforeEach(function () {
            $this->user = User::factory()->create();
            $this->wallet = Wallet::factory()->forUser($this->user)->withBalance(500.00)->create();
        });

        it('can get authenticated user profile', function () {
            $response = $this->actingAs($this->user, 'sanctum')
                ->getJson('/api/auth/me');

            $response->assertStatus(200)
                ->assertJsonStructure([
                    'success',
                    'message',
                    'data' => [
                        'user' => [
                            'id',
                            'name',
                            'email',
                            'wallet' => [
                                'id',
                                'balance',
                                'status'
                            ]
                        ]
                    ]
                ])
                ->assertJson([
                    'success' => true,
                    'data' => [
                        'user' => [
                            'id' => $this->user->id,
                            'email' => $this->user->email
                        ]
                    ]
                ]);
        });

        it('requires authentication to access profile', function () {
            $response = $this->getJson('/api/auth/me');

            $response->assertStatus(401);
        });
    });

    describe('User Logout', function () {

        beforeEach(function () {
            $this->user = User::factory()->create();
            Wallet::factory()->forUser($this->user)->create();
        });

        it('can logout successfully', function () {
            $loginResponse = $this->postJson('/api/auth/login', [
                'email' => $this->user->email,
                'password' => 'password',
            ]);

            $token = $loginResponse->json('data.token');

            $response = $this->withHeaders([
                'Authorization' => 'Bearer ' . $token,
            ])->postJson('/api/auth/logout');

            $response->assertStatus(200)
                ->assertJson([
                    'success' => true,
                    'message' => 'Logged out successfully'
                ]);
        });

        it('requires authentication to logout', function () {
            $response = $this->postJson('/api/auth/logout');

            $response->assertStatus(401);
        });
    });

    describe('Rate Limiting', function () {

        it('enforces rate limits on registration attempts', function () {
            for ($i = 0; $i < 11; $i++) {
                $response = $this->postJson('/api/auth/register', [
                    'name' => "User {$i}",
                    'email' => "user{$i}@example.com",
                    'password' => 'password123',
                    'password_confirmation' => 'password123',
                ]);

                if ($i < 10) {
                    expect($response->status())->not->toBe(429);
                } else {
                    $response->assertStatus(429);
                }
            }
        });

        it('enforces rate limits on login attempts', function () {
            $user = User::factory()->create();

            for ($i = 0; $i < 11; $i++) {
                $response = $this->postJson('/api/auth/login', [
                    'email' => $user->email,
                    'password' => 'wrongpassword',
                ]);

                if ($i < 10) {
                    expect($response->status())->toBe(401);
                } else {
                    $response->assertStatus(429);
                }
            }
        });
    });
});
