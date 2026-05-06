<?php

namespace Database\Factories;

use App\Models\Joke;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Joke>
 */
class JokeFactory extends Factory
{
    protected $model = Joke::class;

    public function definition(): array
    {
        return [
            'external_id' => $this->faker->unique()->numberBetween(1, 1_000_000),
            'type' => $this->faker->randomElement(['general', 'programming']),
            'setup' => $this->faker->sentence(),
            'punchline' => $this->faker->sentence(),
            'fetched_at' => now(),
        ];
    }
}
