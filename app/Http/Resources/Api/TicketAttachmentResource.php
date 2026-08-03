<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use App\Models\TicketAttachment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketAttachment
 */
class TicketAttachmentResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'filename' => $this->filename,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'size_human' => $this->humanReadableSize(),
            'created_at' => $this->created_at->toIso8601String(),
        ];
    }
}
