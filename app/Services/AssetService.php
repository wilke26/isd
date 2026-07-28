<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

class AssetService
{
    public function list(array $filters = []): LengthAwarePaginator
    {
        return Asset::with(['category', 'status', 'currentAssignment.user'])
            ->when(isset($filters['category_id']), fn ($q) => $q->where('asset_category_id', $filters['category_id']))
            ->when(isset($filters['status_id']), fn ($q) => $q->where('asset_status_id', $filters['status_id']))
            ->when(isset($filters['search']), fn ($q) => $q->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('asset_tag', 'like', '%' . $filters['search'] . '%')
                  ->orWhere('serial_number', 'like', '%' . $filters['search'] . '%');
            }))
            ->latest()
            ->paginate($filters['per_page'] ?? 15);
    }

    public function findOrFail(int $id): Asset
    {
        return Asset::with([
            'category',
            'status',
            'parent',
            'children',
            'assignments.user',
            'licenseAssignments.license',
        ])->findOrFail($id);
    }

    public function create(array $data): Asset
    {
        return Asset::create($data)->load(['category', 'status']);
    }

    public function update(Asset $asset, array $data): Asset
    {
        $asset->update($data);
        return $asset->fresh(['category', 'status']);
    }

    public function assign(Asset $asset, User $user): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $user) {
            // Bestehende aktive Zuweisung zurückgeben
            AssetAssignment::where('asset_id', $asset->id)
                ->whereNull('returned_at')
                ->update(['returned_at' => now()]);

            return AssetAssignment::create([
                'asset_id'    => $asset->id,
                'user_id'     => $user->id,
                'assigned_at' => now(),
            ]);
        });
    }

    public function unassign(Asset $asset): void
    {
        AssetAssignment::where('asset_id', $asset->id)
            ->whereNull('returned_at')
            ->update(['returned_at' => now()]);
    }

    public function assignmentHistory(Asset $asset): Collection
    {
        return $asset->assignments()->with('user')->latest('assigned_at')->get();
    }
}
