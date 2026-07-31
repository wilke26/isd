<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Laravel\Sanctum\HasApiTokens;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

/**
 * Class User
 *
 * Represents an application user within the system.
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }

    // ─── Rollen & Berechtigungen ───────────────────────────────────

    /**
     * Get the roles assigned to the user.
     *
     * @return BelongsToMany
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Check if the user has a role with the given slug.
     *
     * @param string $slug
     * @return bool
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    /**
     * Check if the user has a permission with the given slug.
     *
     * @param string $slug
     * @return bool
     */
    public function hasPermission(string $slug): bool
    {
        return $this->roles->flatMap->permissions->contains('slug', $slug);
    }

    // ─── Assets ───────────────────────────────────────────────────

    /**
     * Get the asset assignments for the user.
     *
     * @return HasMany
     */
    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Get the current asset assignments for the user.
     *
     * @return HasMany
     */
    public function currentAssets(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->whereNull('returned_at');
    }

    /**
     * Get the license assignments for the user.
     *
     * @return HasMany
     */
    public function licenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    // ─── Tickets ──────────────────────────────────────────────────

    /**
     * Get the tickets that this user requested.
     *
     * @return HasMany
     */
    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /**
     * Get the tickets that are assigned to this user.
     *
     * @return HasMany
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    /**
     * Get the ticket comments made by the user.
     *
     * @return HasMany
     */
    public function ticketComments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    // ─── Wissensdatenbank ─────────────────────────────────────────

    /**
     * Get the knowledge base articles authored by the user.
     *
     * @return HasMany
     */
    public function kbArticles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'author_id');
    }
}
