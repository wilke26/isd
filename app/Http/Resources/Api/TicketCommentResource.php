<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\TicketComment
 */
class TicketCommentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'          => $this->id,
            'body'        => $this->body,
            'is_internal' => $this->is_internal,
            'user'        => new UserResource($this->whenLoaded('user')),
            'created_at'  => $this->created_at->toIso8601String(),
        ];
    }
}
