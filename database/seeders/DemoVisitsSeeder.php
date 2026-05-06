<?php

namespace Database\Seeders;

use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Sequence;
use Illuminate\Database\Seeder;

class DemoVisitsSeeder extends Seeder
{
    public function run(): void
    {
        Visit::factory()
            ->count(200)
            ->state(new Sequence(
                fn () => ['created_at' => now()->subMinutes(random_int(0, 24 * 60))],
            ))
            ->create();
    }
}
