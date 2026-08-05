<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles\Pages;

use App\Filament\Resources\KbArticles\KbArticleResource;
use App\Models\KbArticle;
use App\Services\KbArticleService;
use Filament\Actions\ViewAction;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditKbArticle extends EditRecord
{
    protected static string $resource = KbArticleResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * Leitet Änderungen durch KbArticleService statt Filaments
     * Standard-Speicherlogik — sorgt bei Titeländerungen dafür, dass der
     * Slug automatisch und race-condition-sicher neu generiert wird.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof KbArticle);

        return app(KbArticleService::class)->update($record, $data);
    }
}
