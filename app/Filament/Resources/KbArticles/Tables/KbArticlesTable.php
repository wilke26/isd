<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles\Tables;

use App\Filament\Resources\KbArticles\KbArticleWorkflowActions;
use Filament\Actions\BulkActionGroup;
use Filament\Actions\DeleteBulkAction;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;

/**
 * Table configuration for the KB article list view in the Filament panel,
 * including the workflow actions from KbArticleWorkflowActions.
 */
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
                ...KbArticleWorkflowActions::make(),
            ])
            ->toolbarActions([
                // Only DeleteBulkAction: matches the existing,
                // policy-driven API capability (admin all, agent their
                // own, requester their own drafts). ForceDeleteBulkAction/
                // RestoreBulkAction deliberately removed — the API never
                // had endpoints for those.
                BulkActionGroup::make([
                    DeleteBulkAction::make(),
                ]),
            ]);
    }
}
