<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Form fields for creating/editing a KB article in the Filament panel.
 */
class KbArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // author_id deliberately not a form field: CreateKbArticle
                // always fixes the author to the logged-in user
                // (KbArticleService::create() expects it separately, not
                // from $data) — the API likewise has no way to choose a
                // different author.
                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name')
                    ->searchable(),
                Select::make('tags')
                    ->relationship('tags', 'name')
                    ->multiple()
                    ->searchable()
                    ->preload(),
                TextInput::make('title')
                    ->required(),
                Textarea::make('body')
                    ->required()
                    ->columnSpanFull(),
                // slug/status/published_at deliberately NOT in the form:
                // - slug is generated automatically by KbArticleService
                //   (including the race-condition-safe retry logic against
                //   slug collisions) — a free text field would bypass that
                //   entirely
                // - status changes exclusively via the dedicated
                //   "Einreichen"/"Veröffentlichen"/"Archivieren" actions,
                //   which route to submit()/publish()/archive() — a
                //   generic dropdown would bypass their conditions
                // - published_at is set by publish(), not manually
            ]);
    }
}
