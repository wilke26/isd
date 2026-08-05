<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets\Schemas;

use App\Models\Asset;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Builder;

class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // --generate hat diese drei Relationen nicht automatisch
                // erkannt (Fremdschlüssel heißen asset_category_id/
                // asset_status_id statt category_id/status_id) und rohe
                // Zahlen-Eingabefelder erzeugt — hier auf echte
                // Beziehungs-Dropdowns umgestellt.
                Select::make('asset_category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->required()
                    ->searchable(),
                Select::make('asset_status_id')
                    ->label('Status')
                    ->relationship('status', 'name')
                    ->required()
                    ->searchable(),
                Select::make('parent_asset_id')
                    ->label('Parent Asset')
                    ->relationship(
                        name: 'parent',
                        titleAttribute: 'name',
                        // Ein Asset darf nicht sein eigenes übergeordnetes
                        // Asset sein — spiegelt die entsprechende Regel aus
                        // UpdateAssetRequest, hier bereits auf UI-Ebene
                        // ausgeschlossen statt erst bei der Validierung.
                        modifyQueryUsing: fn (Builder $query, ?Asset $record) => $record
                            ? $query->whereKeyNot($record->id)
                            : $query,
                    )
                    ->searchable(),
                TextInput::make('asset_tag')
                    ->required()
                    ->unique(ignoreRecord: true),
                TextInput::make('name')
                    ->required(),
                TextInput::make('serial_number'),
                TextInput::make('manufacturer'),
                TextInput::make('model'),
                DatePicker::make('purchased_at'),
                DatePicker::make('warranty_until'),
                Textarea::make('notes')
                    ->columnSpanFull(),
            ]);
    }
}
