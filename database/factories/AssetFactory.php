<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Asset;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use Illuminate\Database\Eloquent\Factories\Factory;

class AssetFactory extends Factory
{
    protected $model = Asset::class;

    public function definition(): array
    {
        return [
            'asset_category_id' => AssetCategory::factory(),
            'asset_status_id' => AssetStatus::factory(),
            'parent_asset_id' => null,
            'asset_tag' => strtoupper(fake()->unique()->bothify('??-###')),
            'name' => fake()->words(3, true),
            'serial_number' => fake()->unique()->bothify('SN-########'),
            'manufacturer' => fake()->randomElement(['Apple', 'Lenovo', 'Dell', 'HP', 'Cisco']),
            'model' => fake()->bothify('Model-??-###'),
            'purchased_at' => fake()->dateTimeBetween('-3 years', '-6 months'),
            'warranty_until' => fake()->dateTimeBetween('now', '+3 years'),
            'notes' => null,
        ];
    }
}
