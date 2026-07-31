<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\Asset
 */
class AssetResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'             => $this->id,
            'asset_tag'      => $this->asset_tag,
            'name'           => $this->name,
            'serial_number'  => $this->serial_number,
            'manufacturer'   => $this->manufacturer,
            'model'          => $this->model,
            'purchased_at'   => $this->purchased_at?->toDateString(),
            'warranty_until' => $this->warranty_until?->toDateString(),
            'notes'          => $this->notes,
            'category'       => $this->whenLoaded('category', fn () => [
                'id'   => $this->category->id,
                'name' => $this->category->name,
            ]),
            'status' => $this->whenLoaded('status', fn () => [
                'id'    => $this->status->id,
                'name'  => $this->status->name,
                'color' => $this->status->color,
            ]),
            'parent' => $this->whenLoaded('parent', fn () => $this->parent ? [
                'id'        => $this->parent->id,
                'asset_tag' => $this->parent->asset_tag,
                'name'      => $this->parent->name,
            ] : null),
            'current_assignment' => $this->whenLoaded('currentAssignment', function () {
                $assignment = $this->currentAssignment->first();
                return $assignment ? [
                    'user'        => new UserResource($assignment->user),
                    'assigned_at' => $assignment->assigned_at->toIso8601String(),
                ] : null;
            }),
            'created_at' => $this->created_at->toIso8601String(),
            'updated_at' => $this->updated_at->toIso8601String(),
        ];
    }
}
