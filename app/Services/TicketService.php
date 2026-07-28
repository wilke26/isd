<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketStatus;
use App\Models\Ticket;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;

class TicketService
{
    /**
     * Gefilterte, paginierte Ticket-Liste.
     * Agents sehen alle Tickets, normale User nur ihre eigenen.
     */
    public function list(User $user, array $filters = []): LengthAwarePaginator
    {
        $query = Ticket::with(['requester', 'assignee', 'category', 'asset'])
            ->when(! $user->hasRole('admin') && ! $user->hasRole('agent'), function ($q) use ($user) {
                $q->where('requester_id', $user->id);
            })
            ->when(isset($filters['status']), fn ($q) => $q->where('status', $filters['status']))
            ->when(isset($filters['priority']), fn ($q) => $q->where('priority', $filters['priority']))
            ->when(isset($filters['assignee_id']), fn ($q) => $q->where('assignee_id', $filters['assignee_id']))
            ->when(isset($filters['search']), fn ($q) => $q->where('title', 'like', '%' . $filters['search'] . '%'))
            ->latest();

        return $query->paginate($filters['per_page'] ?? 15);
    }

    public function findOrFail(int $id): Ticket
    {
        return Ticket::with([
            'requester',
            'assignee',
            'category',
            'asset',
            'comments.user',
            'attachments',
            'history.user',
        ])->findOrFail($id);
    }

    public function create(User $requester, array $data): Ticket
    {
        return DB::transaction(function () use ($requester, $data) {
            $ticket = Ticket::create([
                ...$data,
                'requester_id' => $requester->id,
                'status'       => TicketStatus::Open,
            ]);

            $this->recordHistory($ticket, $requester, 'status', null, TicketStatus::Open->value);

            return $ticket->load(['requester', 'category', 'asset']);
        });
    }

    public function update(Ticket $ticket, User $actor, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $data) {
            foreach (['status', 'priority', 'assignee_id'] as $field) {
                $currentValue = $ticket->{$field} instanceof \BackedEnum
                    ? $ticket->{$field}->value
                    : (string) $ticket->{$field};
                if (isset($data[$field]) && $currentValue !== (string) $data[$field]) {
                    $this->recordHistory($ticket, $actor, $field, $currentValue, (string) $data[$field]);
                }
            }

            // Resolved-Zeitstempel automatisch setzen/löschen
            if (isset($data['status'])) {
                $status = TicketStatus::from($data['status']);
                $data['resolved_at'] = $status === TicketStatus::Resolved ? now() : null;
                $data['closed_at']   = $status === TicketStatus::Closed   ? now() : null;
            }

            $ticket->update($data);

            return $ticket->fresh(['requester', 'assignee', 'category', 'asset']);
        });
    }

    public function addComment(Ticket $ticket, User $author, string $body, bool $isInternal = false): void
    {
        $ticket->comments()->create([
            'user_id'     => $author->id,
            'body'        => $body,
            'is_internal' => $isInternal,
        ]);
    }

    private function recordHistory(Ticket $ticket, User $actor, string $field, ?string $old, ?string $new): void
    {
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id'   => $actor->id,
            'field'     => $field,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
