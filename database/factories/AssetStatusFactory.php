<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssetStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetStatusFactory extends Factory
{
    protected $model = AssetStatus::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Verfügbar', 'In Verwendung', 'In Reparatur']),
            'color' => fake()->hexColor(),
        ];
    }

    public function available(): static
    {
        return $this->state(['name' => 'Verfügbar', 'color' => '#22c55e']);
    }

    public function inUse(): static
    {
        return $this->state(['name' => 'In Verwendung', 'color' => '#3b82f6']);
    }
}
