<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class AssetStatus extends Model
{
    protected $fillable = [
        'name',
        'color',
    ];

    public function assets(): HasMany
    {
        return $this->hasMany(Asset::class);
    }
}
