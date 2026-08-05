<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddKbArticleAddendumRequest;
use App\Http\Requests\Api\StoreKbArticleRequest;
use App\Http\Resources\Api\KbArticleResource;
use App\Models\KbArticle;
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
        $this->authorize('viewAny', KbArticle::class);

        $articles = $this->kbService->list(
            user: $request->user(),
            filters: $request->only(['category_id', 'status', 'tag', 'search', 'per_page']),
        );

        return KbArticleResource::collection($articles);
    }

    public function store(StoreKbArticleRequest $request): JsonResponse
    {
        $this->authorize('create', KbArticle::class);

        $article = $this->kbService->create($request->user(), $request->validated());

        return response()->json(new KbArticleResource($article), 201);
    }

    public function show(int $id): KbArticleResource
    {
        $article = $this->kbService->findOrFail($id);
        $this->authorize('view', $article);

        return new KbArticleResource($article);
    }

    public function update(StoreKbArticleRequest $request, int $id): KbArticleResource
    {
        $article = $this->kbService->findOrFail($id);
        $this->authorize('update', $article);

        return new KbArticleResource($this->kbService->update($article, $request->validated()));
    }

    public function destroy(int $id): JsonResponse
    {
        $article = $this->kbService->findOrFail($id);
        $this->authorize('delete', $article);

        $article->delete();

        return response()->json(['message' => 'Artikel gelöscht.']);
    }

    /** Entwurf zur redaktionellen Prüfung einreichen (Autor, nur aus draft) */
    public function submit(int $id): KbArticleResource
    {
        $article = $this->kbService->findOrFail($id);
        $this->authorize('submit', $article);

        return new KbArticleResource($this->kbService->submit($article));
    }

    /** Veröffentlichen (Staff, aus submitted/draft) */
    public function publish(int $id): KbArticleResource
    {
        $article = $this->kbService->findOrFail($id);
        $this->authorize('publish', $article);

        return new KbArticleResource($this->kbService->publish($article));
    }

    /** Archivieren (Staff, aus published) */
    public function archive(int $id): KbArticleResource
    {
        $article = $this->kbService->findOrFail($id);
        $this->authorize('archive', $article);

        return new KbArticleResource($this->kbService->archive($article));
    }

    /**
     * Zeitgestempelte Ergänzung anhängen (Staff oder ursprünglicher Autor,
     * nur bei bereits veröffentlichten/archivierten Artikeln) — der
     * ursprüngliche Haupttext bleibt dabei unverändert.
     */
    public function addAddendum(AddKbArticleAddendumRequest $request, int $id): KbArticleResource
    {
        $article = $this->kbService->findOrFail($id);
        $this->authorize('addAddendum', $article);

        return new KbArticleResource(
            $this->kbService->addAddendum($article, $request->user(), $request->validated()['text']),
        );
    }
}
