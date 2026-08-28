<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Asset;
use App\Models\AssetAssignment;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Service class for managing assets.
 */
class AssetService
{
    /**
     * Returns a filtered and paginated list of assets.
     * Admins and agents see all assets, other users only the ones assigned to them.
     */
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $isStaff = $user->hasRole('admin') || $user->hasRole('agent');

        return Asset::with(['category', 'status', 'currentAssignment.user'])
            ->when(! $isStaff, function ($q) use ($user) {
                // A requester only sees the assets currently assigned to them
                $q->whereHas('assignments', function ($q2) use ($user) {
                    $q2->where('user_id', $user->id)->whereNull('returned_at');
                });
            })
            ->when(isset($filters['category_id']), fn ($q) => $q->where('asset_category_id', $filters['category_id']))
            ->when(isset($filters['status_id']), fn ($q) => $q->where('asset_status_id', $filters['status_id']))
            ->when(isset($filters['search']), fn ($q) => $q->where(function ($q) use ($filters) {
                $q->where('name', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('asset_tag', 'like', '%' . $filters['search'] . '%')
                    ->orWhere('serial_number', 'like', '%' . $filters['search'] . '%');
            }))
            ->latest()
            ->paginate(max(1, min(100, (int) ($filters['per_page'] ?? 15))));
    }

    /**
     * Finds an asset by its ID or throws an exception.
     * Loads all relevant relations for the detail view.
     */
    public function findOrFail(int $id): Asset
    {
        return Asset::with([
            'category',
            'status',
            'parent',
            'children',
            // "currentAssignment.user" instead of "assignments.user" —
            // consistent with the list query above and with what
            // AssetResource actually uses (current_assignment was previously
            // missing on this route because the wrong relation was loaded
            // here).
            'currentAssignment.user',
            'licenseAssignments.license',
        ])->findOrFail($id);
    }

    /**
     * Creates a new asset.
     */
    public function create(array $data): Asset
    {
        return Asset::create($data)->load(['category', 'status']);
    }

    /**
     * Updates an existing asset.
     */
    public function update(Asset $asset, array $data): Asset
    {
        $asset->update($data);

        return $asset->fresh(['category', 'status']);
    }

    /**
     * Deletes an asset (soft delete).
     */
    public function delete(Asset $asset): void
    {
        $asset->delete();
    }

    /**
     * Assigns an asset to a user. The asset is locked (lockForUpdate) for
     * the duration of the transaction, so that two parallel assignments
     * can't both end up "active".
     */
    public function assign(Asset $asset, User $user): AssetAssignment
    {
        return DB::transaction(function () use ($asset, $user) {
            Asset::whereKey($asset->id)->lockForUpdate()->first();

            AssetAssignment::where('asset_id', $asset->id)
                ->whereNull('returned_at')
                ->update(['returned_at' => now()]);

            return AssetAssignment::create([
                'asset_id' => $asset->id,
                'user_id' => $user->id,
                'assigned_at' => now(),
            ]);
        });
    }

    /**
     * Reverses the assignment of an asset (asset is returned).
     */
    public function unassign(Asset $asset): void
    {
        DB::transaction(function () use ($asset) {
            Asset::whereKey($asset->id)->lockForUpdate()->first();

            AssetAssignment::where('asset_id', $asset->id)
                ->whereNull('returned_at')
                ->update(['returned_at' => now()]);
        });
    }

    /**
     * Returns the complete assignment history of an asset.
     *
     * @return Collection<int, AssetAssignment>
     */
    public function assignmentHistory(Asset $asset): Collection
    {
        /** @var Collection<int, AssetAssignment> $assignments */
        $assignments = $asset->assignments()->with('user')->latest('assigned_at')->get();

        return $assignments;
    }
}
