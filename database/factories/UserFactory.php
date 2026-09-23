<?php

namespace Database\Factories;

use App\Enums\ClassroomRole;
use App\Models\Classroom;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Aluno criado pelo professor: sem e-mail e sem senha (entra pelo link pessoal).
     */
    public function managed(): static
    {
        return $this->state(fn (array $attributes) => [
            'email' => null,
            'email_verified_at' => null,
            'password' => null,
        ]);
    }

    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_admin' => true,
        ]);
    }

    /**
     * Vincula o usuário a uma classe como professor(a).
     */
    public function teacherOf(Classroom $classroom): static
    {
        return $this->afterCreating(fn (User $user) => $user->classrooms()->attach($classroom, ['role' => ClassroomRole::Teacher]));
    }

    /**
     * Vincula o usuário a uma classe como aluno(a).
     */
    public function studentOf(Classroom $classroom): static
    {
        return $this->afterCreating(fn (User $user) => $user->classrooms()->attach($classroom, ['role' => ClassroomRole::Student]));
    }
}
