<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles\Pages;

use App\Filament\Resources\KbArticles\KbArticleResource;
use App\Filament\Resources\KbArticles\KbArticleWorkflowActions;
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
            ...KbArticleWorkflowActions::make(),
        ];
    }

    /**
     * Routes changes through KbArticleService instead of Filament's
     * standard save logic — ensures that on title changes, the slug is
     * regenerated automatically and race-condition-safely.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        assert($record instanceof KbArticle);

        return app(KbArticleService::class)->update($record, $data);
    }
}
