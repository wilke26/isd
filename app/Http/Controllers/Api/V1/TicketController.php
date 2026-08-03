<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTicketRequest;
use App\Http\Requests\Api\UpdateTicketRequest;
use App\Http\Resources\Api\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

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
}
