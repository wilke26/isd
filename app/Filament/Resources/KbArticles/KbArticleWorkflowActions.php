<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles;

use App\Enums\ArticleStatus;
use App\Models\KbArticle;
use App\Services\KbArticleService;
use Filament\Actions\Action;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Illuminate\Validation\ValidationException;

/**
 * Zentrale Definition der drei Workflow-Aktionen (Einreichen/Veröffentlichen/
 * Archivieren), damit sie an allen drei Stellen, an denen sie sinnvoll
 * erreichbar sein sollen — Tabellen-Zeile, View-Seite, Edit-Seite —, exakt
 * gleich funktionieren, statt dreifach dupliziert zu werden.
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
        ];
    }
}
