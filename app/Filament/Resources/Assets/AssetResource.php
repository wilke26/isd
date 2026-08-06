<?php

declare(strict_types=1);

namespace App\Filament\Resources\Assets;

use App\Filament\Resources\Assets\Pages\CreateAsset;
use App\Filament\Resources\Assets\Pages\EditAsset;
use App\Filament\Resources\Assets\Pages\ListAssets;
use App\Filament\Resources\Assets\Pages\ViewAsset;
use App\Filament\Resources\Assets\Schemas\AssetForm;
use App\Filament\Resources\Assets\Schemas\AssetInfolist;
use App\Filament\Resources\Assets\Tables\AssetsTable;
use App\Models\Asset;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament-Ressource für Assets: Formular, Detailansicht und Tabelle
 * verweisen auf die jeweils dedizierten Konfigurationsklassen im Ordner.
 */
class AssetResource extends Resource
{
    protected static ?string $model = Asset::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    // War in der --generate-Rückfrage leer geblieben (Enter übernimmt den
    // Vorschlag dort nicht automatisch) — wirkt sich auf globale Suche und
    // Breadcrumbs aus, daher hier nachgetragen.
    protected static ?string $recordTitleAttribute = 'name';

    public static function form(Schema $schema): Schema
    {
        return AssetForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return AssetInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return AssetsTable::configure($table);
    }

    public static function getRelations(): array
    {
        return [
            //
        ];
    }

    public static function getPages(): array
    {
        return [
            'index' => ListAssets::route('/'),
            'create' => CreateAsset::route('/create'),
            'view' => ViewAsset::route('/{record}'),
            'edit' => EditAsset::route('/{record}/edit'),
        ];
    }

    public static function getRecordRouteBindingEloquentQuery(): Builder
    {
        return parent::getRecordRouteBindingEloquentQuery()
            ->withoutGlobalScopes([
                SoftDeletingScope::class,
            ]);
    }

    /**
     * Policy-Query-Scoping fürs Listen-/Suchergebnis, analog zu
     * AssetService::list(): Ein Requester sieht in der Tabelle nur die ihm
     * aktuell zugewiesenen Assets.
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user->hasRole('admin') && ! $user->hasRole('agent')) {
            $query->whereHas('assignments', function (Builder $q) use ($user) {
                $q->where('user_id', $user->id)->whereNull('returned_at');
            });
        }

        return $query;
    }
}
