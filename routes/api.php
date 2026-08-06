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

Route::prefix('v1')->group(function () {
    Route::post('auth/login', [AuthController::class, 'login'])->middleware('throttle:login');

    Route::middleware('auth:sanctum')->group(function () {

        // Auth
        Route::post('auth/logout', [AuthController::class, 'logout']);
        Route::get('auth/me', [AuthController::class, 'me']);

        // Tickets
        Route::get('tickets', [TicketController::class, 'index']);
        Route::post('tickets', [TicketController::class, 'store']);
        Route::get('tickets/{id}', [TicketController::class, 'show']);
        Route::patch('tickets/{id}', [TicketController::class, 'update']);
        Route::post('tickets/{id}/comments', [TicketController::class, 'addComment']);
        Route::post('tickets/{id}/attachments', [TicketController::class, 'storeAttachment']);
        Route::get('tickets/{id}/attachments/{attachmentId}', [TicketController::class, 'downloadAttachment']);
        Route::delete('tickets/{id}/attachments/{attachmentId}', [TicketController::class, 'destroyAttachment']);

        // Assets
        Route::get('assets', [AssetController::class, 'index']);
        Route::post('assets', [AssetController::class, 'store']);
        Route::get('assets/{id}', [AssetController::class, 'show']);
        Route::patch('assets/{id}', [AssetController::class, 'update']);
        Route::delete('assets/{id}', [AssetController::class, 'destroy']);
        Route::post('assets/{id}/assign', [AssetController::class, 'assign']);
        Route::delete('assets/{id}/assign', [AssetController::class, 'unassign']);
        Route::get('assets/{id}/history', [AssetController::class, 'history']);

        // Wissensdatenbank
        Route::get('kb/articles', [KbArticleController::class, 'index']);
        Route::post('kb/articles', [KbArticleController::class, 'store']);
        Route::get('kb/articles/{id}', [KbArticleController::class, 'show']);
        Route::patch('kb/articles/{id}', [KbArticleController::class, 'update']);
        Route::delete('kb/articles/{id}', [KbArticleController::class, 'destroy']);
        Route::post('kb/articles/{id}/submit', [KbArticleController::class, 'submit']);
        Route::post('kb/articles/{id}/publish', [KbArticleController::class, 'publish']);
        Route::post('kb/articles/{id}/archive', [KbArticleController::class, 'archive']);
        Route::post('kb/articles/{id}/addendum', [KbArticleController::class, 'addAddendum']);
    });
});
