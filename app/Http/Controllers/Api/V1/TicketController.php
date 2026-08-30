<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListTicketsRequest;
use App\Http\Requests\Api\StoreTicketAttachmentRequest;
use App\Http\Requests\Api\StoreTicketRequest;
use App\Http\Requests\Api\UpdateTicketRequest;
use App\Http\Resources\Api\TicketAttachmentResource;
use App\Http\Resources\Api\TicketResource;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * REST API controller for ticket CRUD as well as comments and attachments.
 */
class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    public function index(ListTicketsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ticket::class);

        $tickets = $this->ticketService->list(
            user: $request->user(),
            filters: $request->validated(),
        );

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $this->authorize('create', Ticket::class);

        $ticket = $this->ticketService->create(
            requester: $request->user(),
            data: $request->validated(),
        );

        return (new TicketResource($ticket))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $id): TicketResource
    {
        $ticket = $this->ticketService->findOrFail($id);
        $this->authorize('view', $ticket);

        return new TicketResource($ticket);
    }

    public function update(UpdateTicketRequest $request, int $id): TicketResource
    {
        $ticket = $this->ticketService->findOrFail($id);
        $this->authorize('update', $ticket);

        return new TicketResource(
            $this->ticketService->update($ticket, $request->user(), $request->validated()),
        );
    }

    public function addComment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'body' => ['required', 'string'],
            'is_internal' => ['boolean'],
        ]);

        $ticket = $this->ticketService->findOrFail($id);
        $isInternal = $request->boolean('is_internal');

        $this->authorize($isInternal ? 'commentInternally' : 'commentPublicly', $ticket);

        $this->ticketService->addComment(
            ticket: $ticket,
            author: $request->user(),
            body: $request->string('body')->toString(),
            isInternal: $isInternal,
        );

        return response()->json(['message' => 'Kommentar hinzugefügt.'], 201);
    }

    /** Attach a file to a ticket (staff or the ticket's own requester) */
    public function storeAttachment(StoreTicketAttachmentRequest $request, int $id): JsonResponse
    {
        $ticket = $this->ticketService->findOrFail($id);
        $this->authorize('addAttachment', $ticket);

        $attachment = $this->ticketService->addAttachment(
            ticket: $ticket,
            uploader: $request->user(),
            file: $request->file('file'),
        );

        return (new TicketAttachmentResource($attachment))->response()->setStatusCode(201);
    }

    /**
     * Download a file — permission follows the visibility of the ticket
     * itself (no separate "internal" concept for attachments).
     */
    public function downloadAttachment(int $id, int $attachmentId): StreamedResponse
    {
        $ticket = $this->ticketService->findOrFail($id);
        $this->authorize('view', $ticket);

        $attachment = TicketAttachment::where('ticket_id', $ticket->id)->findOrFail($attachmentId);

        return Storage::disk('local')->download($attachment->path, $attachment->filename);
    }

    /** Delete attachment — staff or whoever uploaded it themselves */
    public function destroyAttachment(int $id, int $attachmentId): JsonResponse
    {
        $ticket = $this->ticketService->findOrFail($id);
        $attachment = TicketAttachment::where('ticket_id', $ticket->id)->findOrFail($attachmentId);

        // Array form: the ticket coming first drives the policy resolution
        // (TicketAttachment has no policy class of its own), both objects
        // are still passed through to the method.
        $this->authorize('deleteAttachment', [$ticket, $attachment]);

        $this->ticketService->deleteAttachment($attachment);

        return response()->json(['message' => 'Anhang gelöscht.']);
    }
}
