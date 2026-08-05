<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles\Schemas;

use App\Models\KbArticle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Schema;

class KbArticleInfolist
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                TextEntry::make('title'),
                TextEntry::make('author.name')
                    ->label('Author'),
                TextEntry::make('category.name')
                    ->label('Category')
                    ->placeholder('-'),
                TextEntry::make('tags.name')
                    ->label('Tags')
                    ->badge()
                    ->placeholder('-'),
                TextEntry::make('body')
                    ->columnSpanFull(),
                TextEntry::make('addendum')
                    ->label('Ergänzungen')
                    ->placeholder('-')
                    ->columnSpanFull(),
                TextEntry::make('status')
                    ->badge(),
                TextEntry::make('slug'),
                TextEntry::make('published_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('created_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('updated_at')
                    ->dateTime()
                    ->placeholder('-'),
                TextEntry::make('deleted_at')
                    ->dateTime()
                    ->visible(fn (KbArticle $record): bool => $record->trashed()),
            ]);
    }
}
