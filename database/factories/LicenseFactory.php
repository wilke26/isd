<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\License;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<License> */
class LicenseFactory extends Factory
{
    protected $model = License::class;

    public function definition(): array
    {
        return [
            'name' => fake()->company() . ' Subscription',
            'vendor' => fake()->company(),
            'product' => fake()->words(2, true),
            'license_key' => fake()->uuid(),
            'seats_total' => fake()->numberBetween(1, 50),
            'expires_at' => fake()->optional()->dateTimeBetween('+1 month', '+2 years'),
        ];
    }

    public function expired(): static
    {
        return $this->state(['expires_at' => now()->subDay()]);
    }
}
