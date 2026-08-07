<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tickets\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Table configuration for the ticket list view in the Filament panel.
 */
class TicketsTable
{
    public static function configure(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('requester.name')
                    ->searchable(),
                TextColumn::make('assignee.name')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('asset.name')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('category.name')
                    ->searchable()
                    ->placeholder('-'),
                TextColumn::make('title')
                    ->searchable(),
                TextColumn::make('status')
                    ->badge(),
                TextColumn::make('priority')
                    ->badge(),
                TextColumn::make('due_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('resolved_at')
                    ->dateTime()
                    ->sortable()
                    ->placeholder('-'),
                TextColumn::make('closed_at')
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
            // No TrashedFilter, no delete actions (single or bulk): tickets
            // never had a DELETE endpoint in the API — the panel
            // deliberately does not introduce a new, untested capability
            // here that didn't exist anywhere before.
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
