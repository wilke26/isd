<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles\Pages;

use App\Filament\Resources\KbArticles\KbArticleResource;
use App\Services\KbArticleService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateKbArticle extends CreateRecord
{
    protected static string $resource = KbArticleResource::class;

    /**
     * Routes creation through KbArticleService instead of Filament's
     * standard save logic — ensures the slug is generated automatically
     * and race-condition-safely (instead of a form field that doesn't
     * exist) and the author is always the logged-in user, exactly as via
     * the API.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(KbArticleService::class)->create(auth()->user(), $data);
    }
}
