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
 * Repräsentiert ein technisches Asset (Hardware/Inventar) im System.
 *
 * @mixin IdeHelperAsset
 */
class Asset extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Die Attribute, die massenzuweisbar sind.
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
     * Die Attribute, die konvertiert werden sollen.
     */
    protected function casts(): array
    {
        return [
            'purchased_at' => 'date',
            'warranty_until' => 'date',
        ];
    }

    /**
     * Gibt die Kategorie des Assets zurück.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(AssetCategory::class, 'asset_category_id');
    }

    /**
     * Gibt den Status des Assets zurück (z.B. Bereit, In Reparatur).
     */
    public function status(): BelongsTo
    {
        return $this->belongsTo(AssetStatus::class, 'asset_status_id');
    }

    /**
     * Gibt das übergeordnete Asset zurück (z.B. Server für eine Festplatte).
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Asset::class, 'parent_asset_id');
    }

    /**
     * Gibt die untergeordneten Assets zurück.
     */
    public function children(): HasMany
    {
        return $this->hasMany(Asset::class, 'parent_asset_id');
    }

    /**
     * Gibt die vollständige Zuweisungshistorie zurück.
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Gibt die aktuelle Zuweisung zurück (falls vorhanden).
     *
     * Als HasOne statt HasMany+first() modelliert, da es laut Geschäftsregel
     * (siehe AssetService::assign(), lockForUpdate) nie mehr als eine aktive
     * Zuweisung (ohne Rückgabedatum) gleichzeitig geben kann.
     */
    public function currentAssignment(): HasOne
    {
        return $this->hasOne(AssetAssignment::class)->whereNull('returned_at');
    }

    /**
     * Gibt die zugewiesenen Software-Lizenzen zurück.
     */
    public function licenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    /**
     * Gibt die mit diesem Asset verknüpften Tickets zurück.
     */
    public function tickets(): HasMany
    {
        return $this->hasMany(Ticket::class);
    }
}
