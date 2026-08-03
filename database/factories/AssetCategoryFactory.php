<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\AssetCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetCategoryFactory extends Factory
{
    protected $model = AssetCategory::class;

    public function definition(): array
    {
        return [
            'name' => fake()->randomElement(['Laptops', 'Server', 'Monitore', 'Netzwerk', 'Drucker']),
            'parent_id' => null,
        ];
    }
}
