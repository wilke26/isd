<?php

declare(strict_types=1);

namespace App\Services;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Exceptions\InvalidTicketStatusTransitionException;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Models\TicketHistory;
use App\Models\User;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

class TicketService
{
    /** @var list<string> */
    private const DETAIL_RELATIONS = [
        'requester',
        'assignee',
        'category',
        'asset',
        'comments.user',
        'attachments',
        'history.user',
    ];

    /**
     * Filtered, paginated ticket list.
     * Agents see all tickets, normal users only their own.
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

        return $query->paginate($this->perPage($filters));
    }

    public function findOrFail(int $id): Ticket
    {
        return Ticket::with(self::DETAIL_RELATIONS)->findOrFail($id);
    }

    /**
     * Resolve a ticket inside the caller's visibility boundary.
     *
     * A requester must not be able to enumerate foreign ticket IDs by
     * distinguishing an authorization response from a missing record. Staff
     * retain their global support view; all other users get a 404 for tickets
     * they do not own.
     */
    public function findVisibleToOrFail(User $user, int $id): Ticket
    {
        return Ticket::with(self::DETAIL_RELATIONS)
            ->when(
                ! $user->hasRole('admin') && ! $user->hasRole('agent'),
                fn ($query) => $query->where('requester_id', $user->id),
            )
            ->findOrFail($id);
    }

    /**
     * $requester is the acting/logged actor (among other things for the
     * history) and by default also the business-level ticket requester.
     *
     * @param int|null $requesterId Only set explicitly by trusted, internal
     *                              callers (e.g. the Filament panel for staff) to create a ticket on
     *                              behalf of another user (e.g. a problem reported by phone). In that
     *                              case it diverges from the business-level requester. The public API
     *                              never uses this parameter — there the requester always remains the
     *                              authenticated user themselves, exactly as before.
     */
    public function create(User $requester, array $data, ?int $requesterId = null): Ticket
    {
        if (
            isset($data['asset_id'])
            && ! $requester->hasRole('admin')
            && ! $requester->hasRole('agent')
            && ! $requester->assetAssignments()
                ->where('asset_id', $data['asset_id'])
                ->whereNull('returned_at')
                ->exists()
        ) {
            throw ValidationException::withMessages([
                'asset_id' => ['Das ausgewählte Asset ist dir nicht aktuell zugewiesen.'],
            ]);
        }

        return DB::transaction(function () use ($requester, $data, $requesterId) {
            $ticket = Ticket::create([
                ...$data,
                'requester_id' => $requesterId ?? $requester->id,
                'status' => TicketStatus::Open,
                // Set explicitly instead of relying on the DB default — Eloquent
                // does not automatically read server-side defaults back into the
                // fresh in-memory object.
                'priority' => $data['priority'] ?? TicketPriority::Medium,
            ]);

            $this->recordHistory($ticket, $requester, 'status', null, TicketStatus::Open->value);

            return $ticket->load(['requester', 'assignee', 'category', 'asset']);
        });
    }

    /**
     * @throws InvalidTicketStatusTransitionException if a disallowed status
     *                                                transition is attempted (e.g. open → closed directly).
     *                                                Applies uniformly to all roles, including admins.
     */
    public function update(Ticket $ticket, User $actor, array $data): Ticket
    {
        // Ticket::casts() casts status/priority as real BackedEnum instances.
        // Our API always delivers raw strings via JSON, but Filament's form
        // fields (see TicketForm) work directly with the already-cast
        // attribute value and therefore pass finished enum instances instead
        // of strings. Normalize to raw values here once, so the rest of the
        // method can work uniformly regardless of the caller (API or
        // Filament).
        $data = array_map(
            fn ($value) => $value instanceof \BackedEnum ? $value->value : $value,
            $data,
        );

        return DB::transaction(function () use ($ticket, $actor, $data) {
            // Locks the ticket row for the duration of the transaction and
            // reads the guaranteed-current state. Without this, two parallel
            // requests could see the same (potentially stale) starting status
            // from the passed-in $ticket object, each validate a transition
            // that's individually valid, and then write conflicting changes
            // with an incorrect history — analogous to AssetService::assign().
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
                // array_key_exists instead of isset: assignee_id is nullable —
                // an explicit "assignee_id": null (clearing the assignment)
                // would be incorrectly treated by isset() as "not sent" and
                // thus neither detected nor logged.
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

            // Only maintain the resolved/closed timestamps if the status
            // actually changed. Otherwise, repeatedly sending the same status
            // (e.g. together with a pure priority or assignment change) would
            // incorrectly reset the resolved/closed time to "now".
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
            // Serialize the comment-driven transition with every other ticket
            // update. The model passed by the caller may already be stale;
            // only the locked row represents the status on which this
            // automatic transition is allowed to act.
            $lockedTicket = Ticket::query()
                ->lockForUpdate()
                ->findOrFail($ticket->id);

            $lockedTicket->comments()->create([
                'user_id' => $author->id,
                'body' => $body,
                'is_internal' => $isInternal,
            ]);

            // Automatic status change: if the requester publicly replies to a
            // ticket that's waiting for their response, it automatically jumps
            // back to "in_progress" — the agent needs to become active again.
            // Internal comments deliberately do not trigger a status change.
            if (
                ! $isInternal
                && $lockedTicket->requester_id === $author->id
                && $lockedTicket->status === TicketStatus::WaitingForRequester
            ) {
                $this->recordHistory(
                    $lockedTicket,
                    $author,
                    'status',
                    TicketStatus::WaitingForRequester->value,
                    TicketStatus::InProgress->value,
                );

                $lockedTicket->update(['status' => TicketStatus::InProgress]);
            }
        });
    }

    /**
     * Stores an attachment on the private storage disk under a random
     * filename (never use the name sent by the client as the path — path
     * traversal risk). The original filename is kept separately in the DB
     * for display purposes.
     */
    public function addAttachment(Ticket $ticket, User $uploader, UploadedFile $file): TicketAttachment
    {
        $disk = (string) config('filesystems.ticket_attachments_disk');
        $storedPath = $file->store("ticket-attachments/{$ticket->id}", $disk);

        if ($storedPath === false) {
            throw new RuntimeException('Der Ticket-Anhang konnte nicht gespeichert werden.');
        }

        try {
            return TicketAttachment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $uploader->id,
                'filename' => $this->safeOriginalFilename($file),
                'disk' => $disk,
                'path' => $storedPath,
                // getMimeType() uses PHP's Fileinfo extension to inspect the
                // actual bytes. getClientMimeType() merely repeats the
                // user-controlled Content-Type header and must not be trusted.
                'mime_type' => $file->getMimeType() ?: 'application/octet-stream',
                'size' => $file->getSize(),
            ]);
        } catch (Throwable $exception) {
            // Do not leave an unreferenced object behind when the DB write
            // fails after a successful filesystem operation.
            Storage::disk($disk)->delete($storedPath);

            throw $exception;
        }
    }

    /** Removes both the file from disk and the DB record. */
    public function deleteAttachment(TicketAttachment $attachment): void
    {
        $deleted = Storage::disk($attachment->disk)->delete($attachment->path);

        if (! $deleted) {
            // Remote disks configured with `throw: false` report transient
            // network or permission failures through the return value. Keep
            // the row as the authoritative reference so deletion can be
            // retried instead of silently orphaning the object.
            throw new RuntimeException('Der Ticket-Anhang konnte nicht aus dem Speicher gelöscht werden.');
        }

        $attachment->delete();
    }

    private function safeOriginalFilename(UploadedFile $file): string
    {
        $clientName = str_replace('\\', '/', $file->getClientOriginalName());
        $filename = str_replace(["\0", "\r", "\n"], '', basename($clientName));

        return $filename !== '' ? $filename : 'attachment';
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

    /** Clamp pagination for trusted internal callers as defense in depth. */
    private function perPage(array $filters): int
    {
        return max(1, min(100, (int) ($filters['per_page'] ?? 15)));
    }
}
