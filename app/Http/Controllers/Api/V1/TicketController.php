<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreTicketRequest;
use App\Http\Requests\Api\UpdateTicketRequest;
use App\Http\Resources\Api\TicketResource;
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
        $tickets = $this->ticketService->list(
            user: $request->user(),
            filters: $request->only(['status', 'priority', 'assignee_id', 'search', 'per_page']),
        );

        return TicketResource::collection($tickets);
    }

    public function store(StoreTicketRequest $request): JsonResponse
    {
        $ticket = $this->ticketService->create(
            requester: $request->user(),
            data: $request->validated(),
        );

        return response()->json(new TicketResource($ticket), 201);
    }

    public function show(int $id): TicketResource
    {
        return new TicketResource($this->ticketService->findOrFail($id));
    }

    public function update(UpdateTicketRequest $request, int $id): TicketResource
    {
        $ticket = $this->ticketService->findOrFail($id);

        return new TicketResource(
            $this->ticketService->update($ticket, $request->user(), $request->validated()),
        );
    }

    public function addComment(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'body'        => ['required', 'string'],
            'is_internal' => ['boolean'],
        ]);

        $ticket = $this->ticketService->findOrFail($id);

        $this->ticketService->addComment(
            ticket: $ticket,
            author: $request->user(),
            body: $request->string('body')->toString(),
            isInternal: $request->boolean('is_internal'),
        );

        return response()->json(['message' => 'Kommentar hinzugefügt.'], 201);
    }
}
