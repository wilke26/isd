<?php

declare(strict_types=1);

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TicketHistory extends Model
{
    // Laravel würde "ticket_histories" ableiten — korrekter Name ist "ticket_history"
    protected $table = 'ticket_history';

    // History-Einträge sind unveränderlich — kein updated_at
    public const UPDATED_AT = null;

    protected $fillable = [
        'ticket_id',
        'user_id',
        'field',
        'old_value',
        'new_value',
    ];

    public function ticket(): BelongsTo
    {
        return $this->belongsTo(Ticket::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
