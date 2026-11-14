<?php

namespace Database\Factories;

use App\Enums\StatusEksemplar;
use App\Models\Book;
use App\Models\BookCopy;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<BookCopy> */
class BookCopyFactory extends Factory
{
    public function definition(): array
    {
        return [
            'book_id' => Book::factory(),
            'copy_code' => 'UJI-EK-'.fake()->unique()->numerify('#####'),
            'status' => StatusEksemplar::Tersedia,
        ];
    }
}
