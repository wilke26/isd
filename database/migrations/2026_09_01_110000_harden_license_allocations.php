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
        $licensesWithoutSeats = DB::table('licenses')
            ->where('seats_total', '<', 1)
            ->pluck('id');

        if ($licensesWithoutSeats->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot harden license allocations with zero-seat licenses; resolve license IDs: %s',
                $licensesWithoutSeats->implode(', '),
            ));
        }

        $invalidIds = DB::table('license_assignments')
            ->whereNull('user_id')
            ->whereNull('asset_id')
            ->pluck('id');

        if ($invalidIds->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot harden license assignments without a target; resolve assignment IDs: %s',
                $invalidIds->implode(', '),
            ));
        }

        $this->assertNoDuplicateTargets('user_id', 'users');
        $this->assertNoDuplicateTargets('asset_id', 'assets');

        $overAllocatedLicenseIds = DB::table('license_assignments')
            ->join('licenses', 'licenses.id', '=', 'license_assignments.license_id')
            ->select('license_assignments.license_id')
            ->groupBy('license_assignments.license_id', 'licenses.seats_total')
            ->havingRaw('COUNT(*) > licenses.seats_total')
            ->pluck('license_assignments.license_id');

        if ($overAllocatedLicenseIds->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot remove the license seat counter; resolve over-allocation for license IDs: %s',
                $overAllocatedLicenseIds->implode(', '),
            ));
        }

        Schema::table('license_assignments', function (Blueprint $table) {
            $table->unique(['license_id', 'user_id'], 'license_assignments_license_user_unique');
            $table->unique(['license_id', 'asset_id'], 'license_assignments_license_asset_unique');
        });

        Schema::table('licenses', function (Blueprint $table) {
            // Usage is derived transactionally from the assignment rows. A
            // writable counter could drift after failures or manual changes.
            $table->dropColumn('seats_used');
        });
    }

    public function down(): void
    {
        Schema::table('licenses', function (Blueprint $table) {
            $table->unsignedInteger('seats_used')->default(0)->after('seats_total');
        });

        DB::table('licenses')->orderBy('id')->eachById(function (object $license): void {
            DB::table('licenses')->where('id', $license->id)->update([
                'seats_used' => DB::table('license_assignments')
                    ->where('license_id', $license->id)
                    ->count(),
            ]);
        });

        Schema::table('license_assignments', function (Blueprint $table) {
            $table->dropUnique('license_assignments_license_user_unique');
            $table->dropUnique('license_assignments_license_asset_unique');
        });
    }

    private function assertNoDuplicateTargets(string $column, string $targetLabel): void
    {
        $duplicateLicenseIds = DB::table('license_assignments')
            ->whereNotNull($column)
            ->select('license_id', $column)
            ->groupBy('license_id', $column)
            ->havingRaw('COUNT(*) > 1')
            ->pluck('license_id');

        if ($duplicateLicenseIds->isNotEmpty()) {
            throw new RuntimeException(sprintf(
                'Cannot enforce unique license assignments for %s; resolve duplicates for license IDs: %s',
                $targetLabel,
                $duplicateLicenseIds->unique()->implode(', '),
            ));
        }
    }
};
