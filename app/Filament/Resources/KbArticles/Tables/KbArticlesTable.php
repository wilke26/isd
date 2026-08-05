<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles\Tables;

use App\Enums\ArticleStatus;
use App\Models\KbArticle;
use App\Services\KbArticleService;
use Filament\Actions\Action;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Validation\ValidationException;

class KbArticlesTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('author.name')
                    ->searchable(),
                TextColumn::make('category.name')
                    ->placeholder('-'),
                TextColumn::make('tags.name')
                    ->badge()
                    ->separator(','),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('published_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('created_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('updated_at')
                    ->dateTime()
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                TrashedFilter::make(),
            ])
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
                // Drei eigene Aktionen statt eines generischen Status-Felds
                // im Formular — leiten auf die entsprechenden
                // KbArticleService-Methoden um, damit deren Bedingungen
                // (nur aus Draft einreichen, nicht doppelt veröffentlichen,
                // nur Veröffentlichtes archivieren) auch im Panel greifen.
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
            ])
            ->toolbarActions([
                // Nur DeleteBulkAction: entspricht der bestehenden,
                // policy-gesteuerten API-Fähigkeit (Admin alle, Agent
                // eigene, Requester eigene Entwürfe). ForceDeleteBulkAction/
                // RestoreBulkAction absichtlich entfernt — die API hatte
                // dafür nie Endpunkte.
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
