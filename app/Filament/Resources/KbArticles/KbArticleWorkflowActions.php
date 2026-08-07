<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles;

use App\Enums\ArticleStatus;
use App\Models\KbArticle;
use App\Services\KbArticleService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Central definition of the workflow actions, so that in every place where
 * they should sensibly be reachable — table row, view page, edit page —
 * they work exactly the same way, instead of being duplicated.
 */
class KbArticleWorkflowActions
{
    /** @return array<Action> */
    public static function make(): array
    {
        return [
            Action::make('submit')
                ->label('Einreichen')
                ->icon(Heroicon::OutlinedPaperAirplane)
                ->color('gray')
                ->requiresConfirmation()
                ->visible(fn (KbArticle $record) => $record->status === ArticleStatus::Draft
                    && auth()->user()->can('submit', $record))
                ->action(function (KbArticle $record): void {
                    try {
                        app(KbArticleService::class)->submit($record);
                        Notification::make()->success()->title('Artikel zur Prüfung eingereicht.')->send();
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                    }
                }),
            Action::make('publish')
                ->label('Veröffentlichen')
                ->icon(Heroicon::OutlinedGlobeAlt)
                ->color('success')
                ->requiresConfirmation()
                ->visible(fn (KbArticle $record) => in_array($record->status, [ArticleStatus::Draft, ArticleStatus::Submitted], true)
                    && auth()->user()->can('publish', $record))
                ->action(function (KbArticle $record): void {
                    try {
                        app(KbArticleService::class)->publish($record);
                        Notification::make()->success()->title('Artikel veröffentlicht.')->send();
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                    }
                }),
            Action::make('archive')
                ->label('Archivieren')
                ->icon(Heroicon::OutlinedArchiveBox)
                ->color('danger')
                ->requiresConfirmation()
                ->visible(fn (KbArticle $record) => $record->status === ArticleStatus::Published
                    && auth()->user()->can('archive', $record))
                ->action(function (KbArticle $record): void {
                    try {
                        app(KbArticleService::class)->archive($record);
                        Notification::make()->success()->title('Artikel archiviert.')->send();
                    } catch (ValidationException $e) {
                        Notification::make()->danger()->title(collect($e->errors())->flatten()->first())->send();
                    }
                }),
            // Only makes sense for articles that are already
            // published/archived — before that, the regular edit right
            // (directly changing the main text) applies instead.
            Action::make('addAddendum')
                ->label('Ergänzen')
                ->icon(Heroicon::OutlinedPencilSquare)
                ->color('gray')
                ->visible(fn (KbArticle $record) => auth()->user()->can('addAddendum', $record))
                ->schema([
                    Textarea::make('text')
                        ->label('Ergänzung')
                        ->helperText('Wird mit Zeitstempel und deinem Namen an den Artikel angehängt — der ursprüngliche Text bleibt unverändert.')
                        ->required()
                        ->rows(4),
                ])
                ->action(function (KbArticle $record, array $data): void {
                    app(KbArticleService::class)->addAddendum($record, auth()->user(), $data['text']);
                    Notification::make()->success()->title('Ergänzung hinzugefügt.')->send();
                }),
        ];
    }
}
