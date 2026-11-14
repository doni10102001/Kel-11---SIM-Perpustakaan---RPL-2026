<?php

namespace Database\Seeders;

use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seeder development (Phase 7A). DemoPerpustakaanSeeder menolak berjalan
     * di luar APP_ENV local/testing atau pada database non-lokal.
     */
    public function run(): void
    {
        $this->call(DemoPerpustakaanSeeder::class);
    }
}
