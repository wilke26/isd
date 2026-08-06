<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Repräsentiert ein Ticket im System.
 *
 * @mixin IdeHelperTicket
 */
class Ticket extends Model
{
    use HasFactory;
    use SoftDeletes;

    /**
     * Die Attribute, die massenzuweisbar sind.
     *
     * @var list<string>
     */
    protected $fillable = [
        'requester_id',
        'assignee_id',
        'asset_id',
        'category_id',
        'title',
        'description',
        'status',
        'priority',
        'due_at',
        'resolved_at',
        'closed_at',
    ];

    /**
     * Die Attribute, die konvertiert werden sollen.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => TicketStatus::class,
            'priority' => TicketPriority::class,
            'due_at' => 'datetime',
            'resolved_at' => 'datetime',
            'closed_at' => 'datetime',
        ];
    }

    /**
     * Gibt den Benutzer zurück, der das Ticket erstellt hat.
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requester_id');
    }

    /**
     * Gibt den Benutzer zurück, dem das Ticket zugewiesen ist.
     */
    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assignee_id');
    }

    /**
     * Gibt das Asset zurück, das mit dem Ticket verknüpft ist.
     */
    public function asset(): BelongsTo
    {
        return $this->belongsTo(Asset::class);
    }

    /**
     * Gibt die Kategorie des Tickets zurück.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(TicketCategory::class);
    }

    /**
     * Gibt alle Kommentare zum Ticket zurück.
     */
    public function comments(): HasMany
    {
        return $this->hasMany(TicketComment::class);
    }

    /**
     * Gibt nur die öffentlichen Kommentare zum Ticket zurück.
     */
    public function publicComments(): HasMany
    {
        return $this->hasMany(TicketComment::class)->where('is_internal', false);
    }

    /**
     * Gibt alle Anhänge des Tickets zurück.
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(TicketAttachment::class);
    }

    /**
     * Gibt die Historie der Änderungen am Ticket zurück.
     */
    public function history(): HasMany
    {
        return $this->hasMany(TicketHistory::class);
    }

    /**
     * Prüft, ob das Ticket offen ist.
     */
    public function isOpen(): bool
    {
        return $this->status === TicketStatus::Open;
    }

    /**
     * Prüft, ob das Ticket geschlossen ist.
     */
    public function isClosed(): bool
    {
        return $this->status === TicketStatus::Closed;
    }
}
