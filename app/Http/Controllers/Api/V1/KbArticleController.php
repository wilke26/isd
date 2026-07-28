<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\StoreKbArticleRequest;
use App\Http\Resources\Api\KbArticleResource;
use App\Services\KbArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class KbArticleController extends Controller
{
    public function __construct(
        private readonly KbArticleService $kbService,
    ) {}

    public function index(Request $request): AnonymousResourceCollection
    {
        $articles = $this->kbService->list(
            user: $request->user(),
            filters: $request->only(['category_id', 'status', 'tag', 'search', 'per_page']),
        );

        return KbArticleResource::collection($articles);
    }

    public function store(StoreKbArticleRequest $request): JsonResponse
    {
        $article = $this->kbService->create($request->user(), $request->validated());

        return response()->json(new KbArticleResource($article), 201);
    }

    public function show(int $id): KbArticleResource
    {
        return new KbArticleResource($this->kbService->findOrFail($id));
    }

    public function update(StoreKbArticleRequest $request, int $id): KbArticleResource
    {
        $article = $this->kbService->findOrFail($id);

        return new KbArticleResource($this->kbService->update($article, $request->validated()));
    }

    public function destroy(int $id): JsonResponse
    {
        $article = $this->kbService->findOrFail($id);
        $article->delete();

        return response()->json(['message' => 'Artikel gelöscht.']);
    }
}
