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

/**
 * Form fields for creating/editing an asset in the Filament panel.
 */
class AssetForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // --generate did not automatically recognize these three
                // relations (foreign keys are named asset_category_id/
                // asset_status_id instead of category_id/status_id) and
                // generated raw number input fields — switched to proper
                // relationship dropdowns here.
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
                        // An asset must not be its own parent asset —
                        // mirrors the corresponding rule from
                        // UpdateAssetRequest, excluded here already at the
                        // UI level instead of only at validation.
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
