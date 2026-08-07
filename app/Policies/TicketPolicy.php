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

    /** The list itself is filtered in the service (requester sees only their own tickets) */
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
        // Any authenticated user may create a ticket
        return true;
    }

    /** Edit, assign, change status — admin/agent only, not the requester */
    public function update(User $user, Ticket $ticket): bool
    {
        return $this->isStaff($user);
    }

    /** Public comment — staff or the ticket's own requester */
    public function commentPublicly(User $user, Ticket $ticket): bool
    {
        return $this->isStaff($user) || $ticket->requester_id === $user->id;
    }

    /** Internal comment — staff only */
    public function commentInternally(User $user, Ticket $ticket): bool
    {
        return $this->isStaff($user);
    }

    /**
     * Upload attachment — same permission as a public comment (staff or
     * the ticket's own requester). Attachments have no "internal" concept
     * like comments do — whoever may view the ticket may also view all of
     * its attachments.
     */
    public function addAttachment(User $user, Ticket $ticket): bool
    {
        return $this->isStaff($user) || $ticket->requester_id === $user->id;
    }

    /** Delete attachment — staff or whoever uploaded it themselves */
    public function deleteAttachment(User $user, Ticket $ticket, TicketAttachment $attachment): bool
    {
        return $this->isStaff($user) || $attachment->user_id === $user->id;
    }
}
