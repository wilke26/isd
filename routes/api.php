<?php

declare(strict_types=1);

use App\Http\Controllers\Api\V1\AssetController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\KbArticleController;
use App\Http\Controllers\Api\V1\TicketController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes — Version 1
|--------------------------------------------------------------------------
*/

// Öffentliche Auth-Routen
Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login']);

    // Alle weiteren Routen benötigen Authentifizierung via Sanctum
    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me',     [AuthController::class, 'me']);

        // Tickets
        Route::get('tickets',                       [TicketController::class, 'index']);
        Route::post('tickets',                      [TicketController::class, 'store']);
        Route::get('tickets/{id}',                  [TicketController::class, 'show']);
        Route::patch('tickets/{id}',                [TicketController::class, 'update']);
        Route::post('tickets/{id}/comments',        [TicketController::class, 'addComment']);

        // Assets
        Route::get('assets',                        [AssetController::class, 'index']);
        Route::post('assets',                       [AssetController::class, 'store']);
        Route::get('assets/{id}',                   [AssetController::class, 'show']);
        Route::patch('assets/{id}',                 [AssetController::class, 'update']);
        Route::post('assets/{id}/assign',           [AssetController::class, 'assign']);
        Route::delete('assets/{id}/assign',         [AssetController::class, 'unassign']);
        Route::get('assets/{id}/history',           [AssetController::class, 'history']);

        // Wissensdatenbank
        Route::get('kb/articles',                   [KbArticleController::class, 'index']);
        Route::post('kb/articles',                  [KbArticleController::class, 'store']);
        Route::get('kb/articles/{id}',              [KbArticleController::class, 'show']);
        Route::patch('kb/articles/{id}',            [KbArticleController::class, 'update']);
        Route::delete('kb/articles/{id}',           [KbArticleController::class, 'destroy']);
    });
});
