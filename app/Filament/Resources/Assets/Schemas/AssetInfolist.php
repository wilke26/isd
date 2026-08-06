<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Schemas;

use App\Models\Asset;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;
use Filament\Support\Colors\Color;

/**
 * Detailansicht (Infolist) eines Assets im Filament-Panel.
 */
class AssetInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('asset_tag'),
                TextEntry::make('name'),
                TextEntry::make('category.name')
                    ->label('Category'),
                TextEntry::make('status.name')
                    ->label('Status')
                    ->badge()
                    // Siehe AssetsTable: AssetStatus ist eine DB-Tabelle mit
                    // rohem Hex-Wert, kein Enum mit HasColor.
                    ->color(fn (Asset $record) => Color::hex($record->status->color)),
                TextEntry::make('parent.name')
                    ->label('Parent Asset')
                    ->placeholder('-'),
                TextEntry::make('currentAssignment.user.name')
                    ->label('Currently Assigned To')
                    ->placeholder('-'),
                TextEntry::make('currentAssignment.assigned_at')
                    ->label('Assigned Since')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('serial_number')
                    ->placeholder('-'),
                TextEntry::make('manufacturer')
                    ->placeholder('-'),
                TextEntry::make('model')
                    ->placeholder('-'),
                TextEntry::make('purchased_at')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('warranty_until')
                    ->date()
                    ->placeholder('-'),
                TextEntry::make('notes')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (Asset $record): bool => $record->trashed()),
            ]);
    }
}
