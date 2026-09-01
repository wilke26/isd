<?php

declare(strict_types=1);

namespace App\Services;

use App\Exceptions\LicenseAssignmentException;
use App\Models\Asset;
use App\Models\License;
use App\Models\LicenseAssignment;
use App\Models\User;
use Illuminate\Support\Facades\DB;

class LicenseService
{
    /** @param array<string, mixed> $data */
    public function create(array $data): License
    {
        $this->assertSeatTotal((int) ($data['seats_total'] ?? 0));

        return License::create($data);
    }

    /** @param array<string, mixed> $data */
    public function update(License $license, array $data): License
    {
        return DB::transaction(function () use ($license, $data): License {
            $lockedLicense = License::query()->lockForUpdate()->findOrFail($license->id);
            $assignedSeats = $lockedLicense->assignments()->count();
            $newSeatTotal = (int) ($data['seats_total'] ?? $lockedLicense->seats_total);

            $this->assertSeatTotal($newSeatTotal);

            if ($newSeatTotal < $assignedSeats) {
                throw new LicenseAssignmentException(sprintf(
                    'Das Kontingent kann nicht unter die %d bestehenden Zuweisungen reduziert werden.',
                    $assignedSeats,
                ));
            }

            $lockedLicense->update($data);

            return $lockedLicense->fresh();
        });
    }

    public function assignToUser(License $license, User $user): LicenseAssignment
    {
        return $this->assign($license, user: $user);
    }

    public function assignToAsset(License $license, Asset $asset): LicenseAssignment
    {
        return $this->assign($license, asset: $asset);
    }

    public function unassign(LicenseAssignment $assignment): void
    {
        DB::transaction(function () use ($assignment): void {
            // Keep the same lock order as assign(): license first, assignment
            // second. This avoids deadlocks between allocation and release.
            License::query()->lockForUpdate()->findOrFail($assignment->license_id);
            LicenseAssignment::query()->lockForUpdate()->findOrFail($assignment->id)->delete();
        });
    }

    private function assign(
        License $license,
        ?User $user = null,
        ?Asset $asset = null,
    ): LicenseAssignment {
        return DB::transaction(function () use ($license, $user, $asset): LicenseAssignment {
            $lockedLicense = License::query()->lockForUpdate()->findOrFail($license->id);

            // A date-only expiry remains valid for the entire stated day.
            if ($lockedLicense->expires_at?->isBefore(today())) {
                throw new LicenseAssignmentException('Eine abgelaufene Lizenz kann nicht zugewiesen werden.');
            }

            if ($lockedLicense->assignments()->count() >= $lockedLicense->seats_total) {
                throw new LicenseAssignmentException('Für diese Lizenz sind keine freien Sitzplätze verfügbar.');
            }

            $duplicate = $lockedLicense->assignments()
                ->when($user !== null, fn ($query) => $query->where('user_id', $user->id))
                ->when($asset !== null, fn ($query) => $query->where('asset_id', $asset->id))
                ->exists();

            if ($duplicate) {
                throw new LicenseAssignmentException('Dieses Ziel besitzt die Lizenz bereits.');
            }

            return LicenseAssignment::create([
                'license_id' => $lockedLicense->id,
                'user_id' => $user?->id,
                'asset_id' => $asset?->id,
                'assigned_at' => now(),
            ]);
        });
    }

    private function assertSeatTotal(int $seatTotal): void
    {
        if ($seatTotal < 1) {
            throw new LicenseAssignmentException('Eine Lizenz benötigt mindestens einen Sitzplatz.');
        }
    }
}
