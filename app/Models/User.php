<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * Repräsentiert einen Benutzer im System.
 *
 * @mixin IdeHelperUser
 */
class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * Die Attribute, die massenzuweisbar sind.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * Die Attribute, die in JSON-Serialisierungen verborgen werden sollen.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Die Attribute, die konvertiert werden sollen.
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    // ─── Rollen & Berechtigungen ───────────────────────────────────

    /**
     * Gibt die Rollen des Benutzers zurück.
     */
    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class);
    }

    /**
     * Prüft, ob der Benutzer eine bestimmte Rolle besitzt.
     */
    public function hasRole(string $slug): bool
    {
        return $this->roles->contains('slug', $slug);
    }

    // ─── Assets ───────────────────────────────────────────────────

    /**
     * Gibt alle Zuweisungen von Assets an diesen Benutzer zurück.
     */
    public function assetAssignments(): HasMany
    {
        return $this->hasMany(AssetAssignment::class);
    }

    /**
     * Gibt die aktuell dem Benutzer zugewiesenen Assets zurück.
     */
    public function currentAssets(): HasMany
    {
        return $this->hasMany(AssetAssignment::class)->whereNull('returned_at');
    }

    /**
     * Gibt die dem Benutzer zugewiesenen Software-Lizenzen zurück.
     */
    public function licenseAssignments(): HasMany
    {
        return $this->hasMany(LicenseAssignment::class);
    }

    // ─── Tickets ──────────────────────────────────────────────────

    /**
     * Gibt die Tickets zurück, die dieser User erstellt hat.
     */
    public function requestedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'requester_id');
    }

    /**
     * Gibt die Tickets zurück, die diesem User zur Bearbeitung zugewiesen sind.
     */
    public function assignedTickets(): HasMany
    {
        return $this->hasMany(Ticket::class, 'assignee_id');
    }

    /**
     * Gibt die Kommentare zurück, die dieser User zu Tickets verfasst hat.
     */
    public function ticketComments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    // ─── Wissensdatenbank ─────────────────────────────────────────

    /**
     * Gibt die Wissensdatenbank-Artikel zurück, deren Autor dieser User ist.
     */
    public function kbArticles(): HasMany
    {
        return $this->hasMany(KbArticle::class, 'author_id');
    }
}
