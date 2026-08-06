<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
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

class TicketController extends Controller
{
    public function __construct(
        private readonly TicketService $ticketService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Ticket::class);

        $tickets = $this->ticketService->list(
            user: $request->user(),
            filters: $request->only(['status', 'priority', 'assignee_id', 'search', 'per_page']),
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

        return response()->json(new TicketResource($ticket), 201);
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

    /** Datei an ein Ticket anhängen (Staff oder der eigene Requester) */
    public function storeAttachment(StoreTicketAttachmentRequest $request, int $id): JsonResponse
    {
        $ticket = $this->ticketService->findOrFail($id);
        $this->authorize('addAttachment', $ticket);

        $attachment = $this->ticketService->addAttachment(
            ticket: $ticket,
            uploader: $request->user(),
            file: $request->file('file'),
        );

        return response()->json(new TicketAttachmentResource($attachment), 201);
    }

    /**
     * Datei herunterladen — Berechtigung folgt der Sichtbarkeit des
     * Tickets selbst (kein separates "intern"-Konzept bei Anhängen).
     */
    public function downloadAttachment(int $id, int $attachmentId): StreamedResponse
    {
        $ticket = $this->ticketService->findOrFail($id);
        $this->authorize('view', $ticket);

        $attachment = TicketAttachment::where('ticket_id', $ticket->id)->findOrFail($attachmentId);

        return Storage::disk('local')->download($attachment->path, $attachment->filename);
    }

    /** Anhang löschen — Staff oder wer ihn selbst hochgeladen hat */
    public function destroyAttachment(int $id, int $attachmentId): JsonResponse
    {
        $ticket = $this->ticketService->findOrFail($id);
        $attachment = TicketAttachment::where('ticket_id', $ticket->id)->findOrFail($attachmentId);

        // Array-Form: das Ticket zuerst steuert die Policy-Auflösung
        // (TicketAttachment hat keine eigene Policy-Klasse), beide Objekte
        // werden trotzdem an die Methode durchgereicht.
        $this->authorize('deleteAttachment', [$ticket, $attachment]);

        $this->ticketService->deleteAttachment($attachment);

        return response()->json(['message' => 'Anhang gelöscht.']);
    }
}
