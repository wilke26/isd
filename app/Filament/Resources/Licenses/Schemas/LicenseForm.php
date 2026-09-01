<?php

declare(strict_types=1);

namespace App\Filament\Resources\Licenses\Schemas;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class LicenseForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema->components([
            TextInput::make('name')
                ->label('Bezeichnung')
                ->required()
                ->maxLength(255),
            TextInput::make('vendor')
                ->label('Hersteller')
                ->required()
                ->maxLength(100),
            TextInput::make('product')
                ->label('Produkt')
                ->required()
                ->maxLength(255),
            TextInput::make('license_key')
                ->label('Lizenzschlüssel')
                ->password()
                ->revealable()
                ->autocomplete('new-password')
                // Never hydrate an existing decrypted key into the HTML.
                // An empty edit field preserves the encrypted DB value.
                ->formatStateUsing(fn (): null => null)
                ->dehydrated(fn (?string $state): bool => filled($state))
                ->helperText('Leer lassen, um einen vorhandenen Schlüssel unverändert zu lassen.'),
            TextInput::make('seats_total')
                ->label('Sitzplätze gesamt')
                ->numeric()
                ->minValue(1)
                ->required(),
            DatePicker::make('expires_at')
                ->label('Gültig bis'),
        ]);
    }
}
