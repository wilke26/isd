<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class License extends Model
{
    use SoftDeletes;

    protected $fillable = [
        'name',
        'vendor',
        'product',
        'license_key',
        'seats_total',
        'seats_used',
        'expires_at',
    ];

    protected function casts(): array
    {
        return [
            // Lizenzschlüssel wird verschlüsselt in der Datenbank gespeichert
            'license_key' => 'encrypted',
            'expires_at'  => 'date',
            'seats_total' => 'integer',
            'seats_used'  => 'integer',
        ];
    }

    public function assignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    public function hasAvailableSeats(): bool
    {
        return $this->seats_used < $this->seats_total;
    }

    public function availableSeats(): int
    {
        return max(0, $this->seats_total - $this->seats_used);
    }
}
