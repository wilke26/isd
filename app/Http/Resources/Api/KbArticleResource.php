<?php

declare(strict_types=1);

namespace App\Http\Resources\Api;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin \App\Models\KbArticle
 */
class KbArticleResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id'           => $this->id,
            'title'        => $this->title,
            'slug'         => $this->slug,
            'body'         => $this->body,
            'status'       => [
                'value' => $this->status->value,
                'label' => $this->status->label(),
            ],
            'author'       => new UserResource($this->whenLoaded('author')),
            'category'     => $this->whenLoaded('category', fn () => $this->category ? [
                'id'   => $this->category->id,
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ] : null),
            'tags'         => $this->whenLoaded('tags', fn () => $this->tags->map(fn ($tag) => [
                'id'   => $tag->id,
                'name' => $tag->name,
                'slug' => $tag->slug,
            ])),
            'published_at' => $this->published_at?->toIso8601String(),
            'created_at'   => $this->created_at->toIso8601String(),
            'updated_at'   => $this->updated_at->toIso8601String(),
        ];
    }
}
