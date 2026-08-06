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
 * Service-Klasse für die Verwaltung von Assets.
 */
class AssetService
{
    /**
     * Gibt eine gefilterte und paginierte Liste von Assets zurück.
     * Administratoren und Agents sehen alle Assets, andere Benutzer nur die ihnen zugewiesenen.
     */
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $isStaff = $user->hasRole('admin') || $user->hasRole('agent');

        return Asset::with(['category', 'status', 'currentAssignment.user'])
            ->when(! $isStaff, function ($q) use ($user) {
                // Requester sieht nur die ihm aktuell zugewiesenen Assets
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
            ->paginate($filters['per_page'] ?? 15);
    }

    /**
     * Findet ein Asset anhand seiner ID oder wirft eine Exception.
     * Lädt alle relevanten Beziehungen für die Detailansicht.
     */
    public function findOrFail(int $id): Asset
    {
        return Asset::with([
            'category',
            'status',
            'parent',
            'children',
            // "currentAssignment.user" statt "assignments.user" — konsistent
            // mit der Listen-Query oben und mit dem, was AssetResource
            // tatsächlich verwendet (current_assignment fehlte zuvor auf
            // dieser Route, weil hier die falsche Relation geladen wurde).
            'currentAssignment.user',
            'licenseAssignments.license',
        ])->findOrFail($id);
    }

    /**
     * Erstellt ein neues Asset.
     */
    public function create(array $data): Asset
    {
        return Asset::create($data)->load(['category', 'status']);
    }

    /**
     * Aktualisiert ein bestehendes Asset.
     */
    public function update(Asset $asset, array $data): Asset
    {
        $asset->update($data);

        return $asset->fresh(['category', 'status']);
    }

    /**
     * Löscht ein Asset (Soft-Delete).
     */
    public function delete(Asset $asset): void
    {
        $asset->delete();
    }

    /**
     * Weist ein Asset einem Benutzer zu. Das Asset wird für die Dauer der
     * Transaktion gesperrt (lockForUpdate), damit zwei parallele Zuweisungen
     * nicht beide als "aktiv" enden können.
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
     * Nimmt die Zuweisung eines Assets zurück (Asset wird zurückgegeben).
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
     * Gibt die vollständige Zuweisungshistorie eines Assets zurück.
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
