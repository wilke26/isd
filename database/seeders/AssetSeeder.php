<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\AssetCategory;
use App\Models\AssetStatus;
use App\Models\User;
use Illuminate\Database\Seeder;

class AssetSeeder extends Seeder
{
    public function run(): void
    {
        // ─── Status ───────────────────────────────────────────────
        $statuses = [
            ['name' => 'Verfügbar',       'color' => '#22c55e'],
            ['name' => 'In Verwendung',   'color' => '#3b82f6'],
            ['name' => 'In Reparatur',    'color' => '#f59e0b'],
            ['name' => 'Ausgemustert',    'color' => '#ef4444'],
            ['name' => 'Lager',           'color' => '#8b5cf6'],
        ];

        foreach ($statuses as $statusData) {
            AssetStatus::firstOrCreate(['name' => $statusData['name']], $statusData);
        }

        $available = AssetStatus::where('name', 'Verfügbar')->first();
        $inUse = AssetStatus::where('name', 'In Verwendung')->first();

        // ─── Categories (hierarchical) ───────────────────────────────
        $hardware = AssetCategory::firstOrCreate(['name' => 'Hardware'], ['parent_id' => null]);
        $software = AssetCategory::firstOrCreate(['name' => 'Software'], ['parent_id' => null]);
        $network = AssetCategory::firstOrCreate(['name' => 'Netzwerk'], ['parent_id' => null]);

        $laptops = AssetCategory::firstOrCreate(['name' => 'Laptops'], ['parent_id' => $hardware->id]);
        $servers = AssetCategory::firstOrCreate(['name' => 'Server'], ['parent_id' => $hardware->id]);
        $monitors = AssetCategory::firstOrCreate(['name' => 'Monitore'], ['parent_id' => $hardware->id]);
        $switches = AssetCategory::firstOrCreate(['name' => 'Switches'], ['parent_id' => $network->id]);

        // ─── Assets ────────────────────────────────────────────────
        $assets = [
            [
                'asset_tag' => 'NB-001',
                'name' => 'MacBook Pro 14"',
                'asset_category_id' => $laptops->id,
                'asset_status_id' => $inUse->id,
                'serial_number' => 'C02XG1JYJGH5',
                'manufacturer' => 'Apple',
                'model' => 'MacBook Pro M3 Pro',
                'purchased_at' => '2024-01-15',
                'warranty_until' => '2027-01-15',
            ],
            [
                'asset_tag' => 'NB-002',
                'name' => 'ThinkPad X1 Carbon',
                'asset_category_id' => $laptops->id,
                'asset_status_id' => $inUse->id,
                'serial_number' => 'PF-2G9T4B',
                'manufacturer' => 'Lenovo',
                'model' => 'ThinkPad X1 Carbon Gen 11',
                'purchased_at' => '2024-03-10',
                'warranty_until' => '2027-03-10',
            ],
            [
                'asset_tag' => 'NB-003',
                'name' => 'Dell XPS 15',
                'asset_category_id' => $laptops->id,
                'asset_status_id' => $available->id,
                'serial_number' => 'DXPS15-7842K',
                'manufacturer' => 'Dell',
                'model' => 'XPS 15 9530',
                'purchased_at' => '2024-06-01',
                'warranty_until' => '2027-06-01',
            ],
            [
                'asset_tag' => 'SRV-001',
                'name' => 'Anwendungsserver 01',
                'asset_category_id' => $servers->id,
                'asset_status_id' => $inUse->id,
                'serial_number' => 'HP-DL380-001',
                'manufacturer' => 'HP',
                'model' => 'ProLiant DL380 Gen10',
                'purchased_at' => '2023-05-20',
                'warranty_until' => '2026-05-20',
            ],
            [
                'asset_tag' => 'SW-001',
                'name' => 'Core Switch EG1',
                'asset_category_id' => $switches->id,
                'asset_status_id' => $inUse->id,
                'serial_number' => 'CISCO-C9300-001',
                'manufacturer' => 'Cisco',
                'model' => 'Catalyst 9300',
                'purchased_at' => '2023-01-10',
                'warranty_until' => '2026-01-10',
            ],
        ];

        foreach ($assets as $assetData) {
            Asset::firstOrCreate(['asset_tag' => $assetData['asset_tag']], $assetData);
        }

        // ─── Assignments ──────────────────────────────────────────────
        $clara = User::where('email', 'c.weber@isd.local')->first();
        $david = User::where('email', 'd.bauer@isd.local')->first();
        $anna = User::where('email', 'a.mueller@isd.local')->first();

        $nb001 = Asset::where('asset_tag', 'NB-001')->first();
        $nb002 = Asset::where('asset_tag', 'NB-002')->first();

        if ($clara && $nb001) {
            AssetAssignment::firstOrCreate(
                ['asset_id' => $nb001->id, 'returned_at' => null],
                ['user_id' => $clara->id, 'assigned_at' => now()->subMonths(3)],
            );
        }

        if ($david && $nb002) {
            AssetAssignment::firstOrCreate(
                ['asset_id' => $nb002->id, 'returned_at' => null],
                ['user_id' => $david->id, 'assigned_at' => now()->subMonths(1)],
            );
        }
    }
}
