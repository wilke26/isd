<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\AddKbArticleAddendumRequest;
use App\Http\Requests\Api\ListKbArticlesRequest;
use App\Http\Requests\Api\StoreKbArticleRequest;
use App\Http\Requests\Api\UpdateKbArticleRequest;
use App\Http\Resources\Api\KbArticleResource;
use App\Models\KbArticle;
use App\Services\KbArticleService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * REST API controller for knowledge base articles: CRUD as well as the
 * workflow (submit/publish/archive/add addendum).
 */
class KbArticleController extends Controller
{
    public function __construct(
        private readonly KbArticleService $kbService,
    ) {}

    public function index(ListKbArticlesRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', KbArticle::class);

        $articles = $this->kbService->list(
            user: $request->user(),
            filters: $request->validated(),
        );

        return KbArticleResource::collection($articles);
    }

    public function store(StoreKbArticleRequest $request): JsonResponse
    {
        $this->authorize('create', KbArticle::class);

        $article = $this->kbService->create($request->user(), $request->validated());

        return (new KbArticleResource($article))->response()->setStatusCode(201);
    }

    public function show(Request $request, int $id): KbArticleResource
    {
        $article = $this->kbService->findVisibleToOrFail($request->user(), $id);
        $this->authorize('view', $article);

        return new KbArticleResource($article);
    }

    public function update(UpdateKbArticleRequest $request, int $id): KbArticleResource
    {
        $article = $this->kbService->findVisibleToOrFail($request->user(), $id);
        $this->authorize('update', $article);

        return new KbArticleResource($this->kbService->update($article, $request->validated()));
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $article = $this->kbService->findVisibleToOrFail($request->user(), $id);
        $this->authorize('delete', $article);

        $this->kbService->delete($article);

        return response()->json(['message' => 'Artikel gelöscht.']);
    }

    /** Submit a draft for editorial review (author, only from draft) */
    public function submit(Request $request, int $id): KbArticleResource
    {
        $article = $this->kbService->findVisibleToOrFail($request->user(), $id);
        $this->authorize('submit', $article);

        return new KbArticleResource($this->kbService->submit($article));
    }

    /** Publish (staff, from submitted/draft) */
    public function publish(Request $request, int $id): KbArticleResource
    {
        $article = $this->kbService->findVisibleToOrFail($request->user(), $id);
        $this->authorize('publish', $article);

        return new KbArticleResource($this->kbService->publish($article));
    }

    /** Archive (staff, from published) */
    public function archive(Request $request, int $id): KbArticleResource
    {
        $article = $this->kbService->findVisibleToOrFail($request->user(), $id);
        $this->authorize('archive', $article);

        return new KbArticleResource($this->kbService->archive($article));
    }

    /**
     * Append a timestamped addendum (staff or original author, only for
     * articles that are already published/archived) — the original main
     * text remains unchanged.
     */
    public function addAddendum(AddKbArticleAddendumRequest $request, int $id): KbArticleResource
    {
        $article = $this->kbService->findVisibleToOrFail($request->user(), $id);
        $this->authorize('addAddendum', $article);

        return new KbArticleResource(
            $this->kbService->addAddendum($article, $request->user(), $request->validated()['text']),
        );
    }
}
