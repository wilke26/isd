<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\Role;
use Illuminate\Database\Eloquent\Factories\Factory;

class RoleFactory extends Factory
{
    protected $model = Role::class;

    public function definition(): array
    {
        $name = fake()->unique()->randomElement(['Administrator', 'Agent', 'Benutzer', 'Manager']);

        return [
            'name' => $name,
            'slug' => \Illuminate\Support\Str::slug($name),
        ];
    }

    public function admin(): static
    {
        return $this->state(['name' => 'Administrator', 'slug' => 'admin']);
    }

    public function agent(): static
    {
        return $this->state(['name' => 'Agent', 'slug' => 'agent']);
    }

    public function user(): static
    {
        return $this->state(['name' => 'Benutzer', 'slug' => 'user']);
    }
}
