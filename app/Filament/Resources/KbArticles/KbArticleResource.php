<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles;

use App\Enums\ArticleStatus;
use App\Filament\Resources\KbArticles\Pages\CreateKbArticle;
use App\Filament\Resources\KbArticles\Pages\EditKbArticle;
use App\Filament\Resources\KbArticles\Pages\ListKbArticles;
use App\Filament\Resources\KbArticles\Pages\ViewKbArticle;
use App\Filament\Resources\KbArticles\Schemas\KbArticleForm;
use App\Filament\Resources\KbArticles\Schemas\KbArticleInfolist;
use App\Filament\Resources\KbArticles\Tables\KbArticlesTable;
use App\Models\KbArticle;
use BackedEnum;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Support\Icons\Heroicon;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\SoftDeletingScope;

/**
 * Filament-Ressource für Knowledge-Base-Artikel: Formular, Detailansicht
 * und Tabelle verweisen auf die jeweils dedizierten Konfigurationsklassen
 * im Ordner.
 */
class KbArticleResource extends Resource
{
    protected static ?string $model = KbArticle::class;

    protected static string|BackedEnum|null $navigationIcon = Heroicon::OutlinedRectangleStack;

    protected static ?string $recordTitleAttribute = 'title';

    public static function form(Schema $schema): Schema
    {
        return KbArticleForm::configure($schema);
    }

    public static function infolist(Schema $schema): Schema
    {
        return KbArticleInfolist::configure($schema);
    }

    public static function table(Table $table): Table
    {
        return KbArticlesTable::configure($table);
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
            'index' => ListKbArticles::route('/'),
            'create' => CreateKbArticle::route('/create'),
            'view' => ViewKbArticle::route('/{record}'),
            'edit' => EditKbArticle::route('/{record}/edit'),
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
     * KbArticleService::list(): Staff sieht alles, ein Requester sieht
     * veröffentlichte Artikel sowie ausschließlich die eigenen (unabhängig
     * vom Status).
     */
    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $user = auth()->user();

        if (! $user->hasRole('admin') && ! $user->hasRole('agent')) {
            $query->where(function (Builder $q) use ($user) {
                $q->where('status', ArticleStatus::Published)
                    ->orWhere('author_id', $user->id);
            });
        }

        return $query;
    }
}
