<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Permission extends Model
{
    protected $fillable = [
        'role_id',
        'name',
        'slug',
    ];

    public function role(): BelongsTo
    {
        return $this->belongsTo(Role::class);
    }
}
