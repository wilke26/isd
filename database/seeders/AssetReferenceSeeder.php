<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\AssetCategory;
use App\Models\AssetStatus;
use Illuminate\Database\Seeder;

class AssetReferenceSeeder extends Seeder
{
    public function run(): void
    {
        $statuses = [
            ['name' => 'Verfügbar', 'color' => '#22c55e'],
            ['name' => 'In Verwendung', 'color' => '#3b82f6'],
            ['name' => 'In Reparatur', 'color' => '#f59e0b'],
            ['name' => 'Ausgemustert', 'color' => '#ef4444'],
            ['name' => 'Lager', 'color' => '#8b5cf6'],
        ];

        foreach ($statuses as $statusData) {
            AssetStatus::firstOrCreate(['name' => $statusData['name']], $statusData);
        }

        $hardware = AssetCategory::firstOrCreate(['name' => 'Hardware'], ['parent_id' => null]);
        AssetCategory::firstOrCreate(['name' => 'Software'], ['parent_id' => null]);
        $network = AssetCategory::firstOrCreate(['name' => 'Netzwerk'], ['parent_id' => null]);

        AssetCategory::firstOrCreate(['name' => 'Laptops'], ['parent_id' => $hardware->id]);
        AssetCategory::firstOrCreate(['name' => 'Server'], ['parent_id' => $hardware->id]);
        AssetCategory::firstOrCreate(['name' => 'Monitore'], ['parent_id' => $hardware->id]);
        AssetCategory::firstOrCreate(['name' => 'Switches'], ['parent_id' => $network->id]);
    }
}
