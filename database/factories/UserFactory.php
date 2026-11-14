<?php

namespace Database\Factories;

use App\Enums\Peran;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Factory pengujian. Kata sandi acak per proses (tidak ada kata sandi tetap
 * di source code).
 *
 * Default = akun Anggota dengan name NULL (D-01: nama Anggota hanya di
 * members.name). State admin() mengisi name karena wajib bagi Admin.
 *
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => 'user_'.Str::lower(Str::random(12)),
            'name' => null,
            'password' => static::$password ??= Hash::make(Str::random(32)),
            'role' => Peran::Anggota,
            'is_active' => true,
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => [
            'role' => Peran::Admin,
            'name' => fake()->name(),
        ]);
    }
}
