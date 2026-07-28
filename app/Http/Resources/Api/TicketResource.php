<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class TicketResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'title'       => $this->title,
            'description' => $this->description,
            'status'      => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
                'color' => $this->status->color(),
            ],
            'priority' => [
                'value' => $this->priority->value,
                'label' => $this->priority->label(),
                'color' => $this->priority->color(),
            ],
            'requester'   => new UserResource($this->whenLoaded('requester')),
            'assignee'    => new UserResource($this->whenLoaded('assignee')),
            'category'    => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'asset' => $this->whenLoaded('asset', fn () => [
                'id'        => $this->asset->id,
                'asset_tag' => $this->asset->asset_tag,
                'name'      => $this->asset->name,
            ]),
            'comments'    => TicketCommentResource::collection($this->whenLoaded('comments')),
            'attachments' => TicketAttachmentResource::collection($this->whenLoaded('attachments')),
            'history'     => TicketHistoryResource::collection($this->whenLoaded('history')),
            'due_at'      => $this->due_at?->toIso8601String(),
            'resolved_at' => $this->resolved_at?->toIso8601String(),
            'closed_at'   => $this->closed_at?->toIso8601String(),
            'created_at'  => $this->created_at->toIso8601String(),
            'updated_at'  => $this->updated_at->toIso8601String(),
        ];
    }
}
