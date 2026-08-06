<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tickets\Tables;

use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

/**
 * Tabellenkonfiguration für die Ticket-Listenansicht im Filament-Panel.
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
            // Kein TrashedFilter, keine Lösch-Aktionen (einzeln oder Bulk):
            // Tickets hatten in der API nie einen DELETE-Endpunkt — das
            // Panel führt hier bewusst keine neue, ungetestete Fähigkeit
            // ein, die es vorher nirgends gab.
            ->recordActions([
                ViewAction::make(),
                EditAction::make(),
            ]);
    }
}
