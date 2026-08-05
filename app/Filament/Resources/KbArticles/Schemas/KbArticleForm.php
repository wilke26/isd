<?php

declare(strict_types=1);

namespace App\Filament\Resources\KbArticles\Schemas;

use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class KbArticleForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // author_id absichtlich kein Formularfeld: CreateKbArticle
                // setzt den Autor immer fest auf den eingeloggten Nutzer
                // (KbArticleService::create() erwartet ihn separat, nicht
                // aus $data) — die API kennt ebenfalls keine Möglichkeit,
                // einen abweichenden Autor zu wählen.
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
                // slug/status/published_at bewusst NICHT im Formular:
                // - slug wird von KbArticleService automatisch generiert
                //   (inkl. der Race-Condition-sicheren Retry-Logik gegen
                //   Slug-Kollisionen) — ein freies Textfeld würde das
                //   komplett umgehen
                // - status wechselt ausschließlich über die dedizierten
                //   Aktionen "Einreichen"/"Veröffentlichen"/"Archivieren",
                //   die auf submit()/publish()/archive() umleiten — ein
                //   generisches Dropdown würde deren Bedingungen umgehen
                // - published_at wird von publish() gesetzt, nicht manuell
            ]);
    }
}
