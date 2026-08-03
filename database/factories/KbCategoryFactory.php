<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\KbCategory;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class KbCategoryFactory extends Factory
{
    protected $model = KbCategory::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Hardware', 'Software', 'Netzwerk', 'Sicherheit']);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'parent_id' => null,
        ];
    }
}
