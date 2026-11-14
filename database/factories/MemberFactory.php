<?php

namespace Database\Factories;

use App\Enums\StatusAnggota;
use App\Models\Member;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Member> */
class MemberFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => null,
            'member_code' => 'UJI-AG-'.fake()->unique()->numerify('#####'),
            'name' => fake()->name(),
            'contact' => fake()->numerify('08##-####-####'),
            'status' => StatusAnggota::Aktif,
        ];
    }

    public function nonaktif(): static
    {
        return $this->state(fn () => ['status' => StatusAnggota::Nonaktif]);
    }
}
