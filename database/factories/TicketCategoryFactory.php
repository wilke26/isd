<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\TicketCategory;
use Illuminate\Database\Eloquent\Factories\Factory;

class TicketCategoryFactory extends Factory
{
    protected $model = TicketCategory::class;

    public function definition(): array
    {
        return [
            'name'      => fake()->randomElement(['Hardware', 'Software', 'Netzwerk', 'Zugangsdaten']),
            'parent_id' => null,
        ];
    }
}
