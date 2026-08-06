<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\User;

class TicketPolicy
{
    private function isStaff(User $user): bool
    {
        return $user->hasRole('admin') || $user->hasRole('agent');
    }

    /** Liste selbst wird im Service gefiltert (Requester sieht nur eigene Tickets) */
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Ticket $ticket): bool
    {
        return $this->isStaff($user) || $ticket->requester_id === $user->id;
    }

    public function create(User $user): bool
    {
        // Jeder authentifizierte Benutzer darf ein Ticket erstellen
        return true;
    }

    /** Bearbeiten, Zuweisen, Status ändern — nur Admin/Agent, nicht der Requester */
    public function update(User $user, Ticket $ticket): bool
    {
        return $this->isStaff($user);
    }

    /** Öffentlicher Kommentar — Staff oder der eigene Requester */
    public function commentPublicly(User $user, Ticket $ticket): bool
    {
        return $this->isStaff($user) || $ticket->requester_id === $user->id;
    }

    /** Interner Kommentar — ausschließlich Staff */
    public function commentInternally(User $user, Ticket $ticket): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Anhang hochladen — dieselbe Berechtigung wie ein öffentlicher
     * Kommentar (Staff oder der eigene Requester). Anhänge haben kein
     * "intern"-Konzept wie Kommentare — wer das Ticket sehen darf, darf
     * auch alle seine Anhänge sehen.
     */
    public function addAttachment(User $user, Ticket $ticket): bool
    {
        return $this->isStaff($user) || $ticket->requester_id === $user->id;
    }

    /** Anhang löschen — Staff oder wer ihn selbst hochgeladen hat */
    public function deleteAttachment(User $user, Ticket $ticket, TicketAttachment $attachment): bool
    {
        return $this->isStaff($user) || $attachment->user_id === $user->id;
    }
}
