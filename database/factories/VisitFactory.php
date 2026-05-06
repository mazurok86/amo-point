<?php

namespace Database\Factories;

use App\Models\Visit;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Visit>
 */
class VisitFactory extends Factory
{
    protected $model = Visit::class;

    public function definition(): array
    {
        return [
            'host' => $this->faker->randomElement(['example.com', 'demo.test', 'shop.local']),
            'visitor_uid' => (string) Str::uuid(),
            'ip' => $this->faker->ipv4(),
            'country' => $this->faker->countryCode(),
            'city' => $this->faker->randomElement([
                'Moscow', 'Saint Petersburg', 'Kazan', 'Novosibirsk',
                'Berlin', 'London', 'Paris', 'Madrid',
                'New York', 'San Francisco', 'Tokyo', 'Singapore',
            ]),
            'device' => $this->faker->randomElement(['desktop', 'mobile', 'tablet']),
            'browser' => $this->faker->randomElement(['Chrome', 'Firefox', 'Safari', 'Edge']),
            'os' => $this->faker->randomElement(['Windows', 'macOS', 'Linux', 'Android', 'iOS']),
            'page_url' => $this->faker->url(),
            'referrer' => null,
            'created_at' => now(),
        ];
    }
}
