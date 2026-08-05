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
     * Leitet die Erstellung durch KbArticleService statt Filaments
     * Standard-Speicherlogik — sorgt dafür, dass der Slug automatisch und
     * race-condition-sicher generiert wird (statt eines nicht vorhandenen
     * Formularfelds) und der Autor immer der eingeloggte Nutzer ist, exakt
     * wie über die API.
     */
    protected function handleRecordCreation(array $data): Model
    {
        return app(KbArticleService::class)->create(auth()->user(), $data);
    }
}
