<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * @mixin IdeHelperAsset
 */
class Asset extends Model
{
    use HasFactory;
    use SoftDeletes;

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

    protected function casts(): array
    {
        return [
            'purchased_at'   => 'date',
            'warranty_until' => 'date',
        ];
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    public function status(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'asset_status_id');
    }

    /** Übergeordnetes Asset (z.B. Server für eine Festplatte) */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'parent_asset_id');
    }

    /** Untergeordnete Assets */
    public function children(): HasMany
    {
        return $this->hasMany(Asset::class, 'parent_asset_id');
    }

    /** Vollständige Zuweisungshistorie */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /** Aktuelle Zuweisung (returned_at ist null) */
    public function currentAssignment(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->whereNull('returned_at');
    }

    public function licenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
