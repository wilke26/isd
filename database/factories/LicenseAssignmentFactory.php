<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\License;
use App\Models\LicenseAssignment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<LicenseAssignment> */
class LicenseAssignmentFactory extends Factory
{
    protected $model = LicenseAssignment::class;

    public function definition(): array
    {
        return [
            'license_id' => License::factory(),
            'user_id' => User::factory(),
            'asset_id' => null,
            'assigned_at' => now(),
        ];
    }
}
