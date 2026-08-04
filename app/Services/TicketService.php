<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketStatusTransitionException;
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
                'status' => TicketStatus::Open,
                // Explizit setzen statt auf den DB-Default zu vertrauen — Eloquent
                // liest server-seitige Defaults nicht automatisch ins frische
                // In-Memory-Objekt zurück.
                'priority' => $data['priority'] ?? TicketPriority::Medium,
            ]);

            $this->recordHistory($ticket, $requester, 'status', null, TicketStatus::Open->value);

            return $ticket->load(['requester', 'category', 'asset']);
        });
    }

    /**
     * @throws InvalidTicketStatusTransitionException wenn ein unzulässiger
     *                                                Statusübergang versucht wird (z.B. open → closed direkt).
     *                                                Gilt einheitlich für alle Rollen, auch Admins.
     */
    public function update(Ticket $ticket, User $actor, array $data): Ticket
    {
        return DB::transaction(function () use ($ticket, $actor, $data) {
            // Sperrt die Ticket-Zeile für die Dauer der Transaktion und liest
            // den garantiert aktuellen Stand. Ohne das könnten zwei parallele
            // Requests denselben (potenziell veralteten) Ausgangsstatus vom
            // übergebenen $ticket-Objekt sehen, beide einen für sich gültigen
            // Übergang prüfen und anschließend widersprüchliche Änderungen
            // mit falscher Historie schreiben — analog zu AssetService::assign().
            $lockedTicket = Ticket::whereKey($ticket->id)->lockForUpdate()->firstOrFail();

            if (isset($data['status'])) {
                $targetStatus = TicketStatus::from($data['status']);

                if (! $lockedTicket->status->canTransitionTo($targetStatus)) {
                    throw new InvalidTicketStatusTransitionException(
                        $lockedTicket->status->value,
                        $targetStatus->value,
                    );
                }
            }

            $statusActuallyChanged = false;

            foreach (['status', 'priority', 'assignee_id'] as $field) {
                // array_key_exists statt isset: assignee_id ist nullable —
                // ein explizites "assignee_id": null (Zuweisung aufheben)
                // würde von isset() fälschlich als "nicht mitgeschickt"
                // behandelt und damit weder erkannt noch protokolliert.
                if (! array_key_exists($field, $data)) {
                    continue;
                }

                $currentValue = match (true) {
                    $lockedTicket->{$field} instanceof \BackedEnum => $lockedTicket->{$field}->value,
                    $lockedTicket->{$field} === null => null,
                    default => (string) $lockedTicket->{$field},
                };

                $newValue = $data[$field] !== null ? (string) $data[$field] : null;

                if ($currentValue !== $newValue) {
                    $this->recordHistory($lockedTicket, $actor, $field, $currentValue, $newValue);

                    if ($field === 'status') {
                        $statusActuallyChanged = true;
                    }
                }
            }

            // Resolved-/Closed-Zeitstempel nur pflegen, wenn sich der Status
            // tatsächlich geändert hat. Andernfalls würde ein wiederholtes
            // Mitschicken desselben Status (z.B. zusammen mit einer reinen
            // Prioritäts- oder Zuweisungsänderung) den Lösungs- bzw.
            // Schließzeitpunkt fälschlich auf "jetzt" zurücksetzen.
            if ($statusActuallyChanged && isset($data['status'])) {
                $status = TicketStatus::from($data['status']);

                if ($status === TicketStatus::Resolved) {
                    $data['resolved_at'] = now();
                } elseif ($status !== TicketStatus::Closed) {
                    $data['resolved_at'] = null;
                }
                $data['closed_at'] = $status === TicketStatus::Closed ? now() : null;
            }

            $lockedTicket->update($data);

            return $lockedTicket->fresh(['requester', 'assignee', 'category', 'asset']);
        });
    }

    public function addComment(Ticket $ticket, User $author, string $body, bool $isInternal = false): void
    {
        DB::transaction(function () use ($ticket, $author, $body, $isInternal) {
            $ticket->comments()->create([
                'user_id' => $author->id,
                'body' => $body,
                'is_internal' => $isInternal,
            ]);

            // Automatischer Statuswechsel: Antwortet der Requester öffentlich auf
            // ein Ticket, das auf seine Rückmeldung wartet, springt es automatisch
            // zurück auf "in_progress" — der Agent muss wieder aktiv werden.
            // Interne Kommentare lösen bewusst keinen Statuswechsel aus.
            if (
                ! $isInternal
                && $ticket->requester_id === $author->id
                && $ticket->status === TicketStatus::WaitingForRequester
            ) {
                $this->recordHistory(
                    $ticket,
                    $author,
                    'status',
                    TicketStatus::WaitingForRequester->value,
                    TicketStatus::InProgress->value,
                );

                $ticket->update(['status' => TicketStatus::InProgress]);
            }
        });
    }

    private function recordHistory(Ticket $ticket, User $actor, string $field, ?string $old, ?string $new): void
    {
        TicketHistory::create([
            'ticket_id' => $ticket->id,
            'user_id' => $actor->id,
            'field' => $field,
            'old_value' => $old,
            'new_value' => $new,
        ]);
    }
}
