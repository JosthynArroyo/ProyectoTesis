<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'name' => $this->faker->name(),
            'email' => $this->faker->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'active' => true,
            'status' => User::STATUS_ACTIVE,
            'suspended_until' => null,
            'remember_token' => Str::random(10),
            'precio_consulta' => null,
            'moneda' => 'USD',
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn () => [
            'status' => User::STATUS_INACTIVE,
            'active' => false,
            'suspended_until' => null,
        ]);
    }

    public function blocked(): static
    {
        return $this->state(fn () => [
            'status' => User::STATUS_BLOCKED,
            'active' => false,
            'suspended_until' => null,
        ]);
    }

    public function suspended(): static
    {
        return $this->state(fn () => [
            'status' => User::STATUS_ACTIVE,
            'active' => true,
            'suspended_until' => now()->addDay(),
        ]);
    }
}
