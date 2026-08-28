<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Api\ListAssetsRequest;
use App\Http\Requests\Api\StoreAssetRequest;
use App\Http\Requests\Api\UpdateAssetRequest;
use App\Http\Resources\Api\AssetResource;
use App\Models\Asset;
use App\Models\User;
use App\Services\AssetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

/**
 * REST API controller for asset CRUD as well as assignment/release and history.
 */
class AssetController extends Controller
{
    public function __construct(
        private readonly AssetService $assetService,
    ) {}

    public function index(ListAssetsRequest $request): AnonymousResourceCollection
    {
        $this->authorize('viewAny', Asset::class);

        $assets = $this->assetService->list(
            user: $request->user(),
            filters: $request->validated(),
        );

        return AssetResource::collection($assets);
    }

    public function store(StoreAssetRequest $request): JsonResponse
    {
        $this->authorize('create', Asset::class);

        $asset = $this->assetService->create($request->validated());

        return response()->json(new AssetResource($asset), 201);
    }

    public function show(int $id): AssetResource
    {
        $asset = $this->assetService->findOrFail($id);
        $this->authorize('view', $asset);

        return new AssetResource($asset);
    }

    public function update(UpdateAssetRequest $request, int $id): AssetResource
    {
        $asset = $this->assetService->findOrFail($id);
        $this->authorize('update', $asset);

        return new AssetResource($this->assetService->update($asset, $request->validated()));
    }

    public function destroy(int $id): JsonResponse
    {
        $asset = $this->assetService->findOrFail($id);
        $this->authorize('delete', $asset);

        $this->assetService->delete($asset);

        return response()->json(['message' => "Asset {$asset->asset_tag} wurde gelöscht."]);
    }

    public function assign(Request $request, int $id): JsonResponse
    {
        $request->validate([
            'user_id' => ['required', 'exists:users,id'],
        ]);

        $asset = $this->assetService->findOrFail($id);
        $this->authorize('assign', $asset);

        $user = User::findOrFail($request->integer('user_id'));

        $this->assetService->assign($asset, $user);

        return response()->json(['message' => "Asset {$asset->asset_tag} wurde {$user->name} zugewiesen."]);
    }

    public function unassign(int $id): JsonResponse
    {
        $asset = $this->assetService->findOrFail($id);
        // No dedicated 'unassign' ability — whoever may assign may also
        // release, both are the same staff-only operation.
        $this->authorize('assign', $asset);

        $this->assetService->unassign($asset);

        return response()->json(['message' => "Zuweisung für {$asset->asset_tag} aufgehoben."]);
    }

    public function history(int $id): JsonResponse
    {
        $asset = $this->assetService->findOrFail($id);
        // Own ability instead of 'view' — the history also shows previous
        // owners by name, which a requester may not see, even if the asset
        // is currently assigned to them.
        $this->authorize('viewHistory', $asset);

        $assignments = $this->assetService->assignmentHistory($asset);

        return response()->json($assignments->map(fn ($a) => [
            'user' => ['id' => $a->user->id, 'name' => $a->user->name],
            'assigned_at' => $a->assigned_at->toIso8601String(),
            'returned_at' => $a->returned_at?->toIso8601String(),
        ]));
    }
}
