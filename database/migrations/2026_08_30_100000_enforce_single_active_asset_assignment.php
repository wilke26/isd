<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $duplicateAssetIds = DB::table('asset_assignments')
            ->whereNull('returned_at')
            ->select('asset_id')
            ->groupBy('asset_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('asset_id');

        if ($duplicateAssetIds->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot enforce one active asset assignment; resolve duplicates for asset IDs: %s',
                $duplicateAssetIds->implode(', '),
            ));
        }

        Schema::table('asset_assignments', function (Blueprint $table) {
            // Unique indexes allow multiple NULL values. Mapping only active
            // assignments to their asset ID therefore enforces one active row
            // per asset while preserving an unlimited assignment history.
            // A virtual column works with both MySQL and the SQLite test DB.
            $table->unsignedBigInteger('active_asset_id')
                ->nullable()
                ->virtualAs('CASE WHEN returned_at IS NULL THEN asset_id ELSE NULL END');

            $table->unique('active_asset_id', 'asset_assignments_one_active_unique');
            $table->index(
                ['user_id', 'returned_at', 'asset_id'],
                'asset_assignments_user_current_index',
            );
        });
    }

    public function down(): void
    {
        Schema::table('asset_assignments', function (Blueprint $table) {
            $table->dropIndex('asset_assignments_user_current_index');
            $table->dropUnique('asset_assignments_one_active_unique');
            $table->dropColumn('active_asset_id');
        });
    }
};
