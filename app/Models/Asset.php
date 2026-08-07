<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Represents a technical asset (hardware/inventory) in the system.
 *
 * @mixin IdeHelperAsset
 */
class Asset extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * The attributes that are mass assignable.
     */
    protected $fillable = [
        'asset_category_id',
        'asset_status_id',
        'parent_asset_id',
        'asset_tag',
        'name',
        'serial_number',
        'manufacturer',
        'model',
        'purchased_at',
        'warranty_until',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     */
    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'warranty_until' => 'date',
        ];
    }

    /**
     * Returns the asset's category.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * Returns the asset's status (e.g. "Bereit", "In Reparatur").
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'asset_status_id');
    }

    /**
     * Returns the parent asset (e.g. a server for a hard drive).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'parent_asset_id');
    }

    /**
     * Returns the child assets.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Asset::class, 'parent_asset_id');
    }

    /**
     * Returns the complete assignment history.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Returns the current assignment (if any).
     *
     * Modeled as HasOne instead of HasMany+first() because, per business
     * rule (see AssetService::assign(), lockForUpdate), there can never be
     * more than one active assignment (without a return date) at a time.
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at');
    }

    /**
     * Returns the assigned software licenses.
     */
    public function licenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    /**
     * Returns the tickets linked to this asset.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
