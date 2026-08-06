<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tickets\Schemas;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Ticket;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

/**
 * Formularfelder für Anlegen/Bearbeiten eines Tickets im Filament-Panel.
 */
class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Nur Staff darf beim Anlegen einen abweichenden Requester
                // wählen (z.B. telefonisch gemeldetes Problem) — nicht-Staff
                // sieht das Feld gar nicht, requester_id wird dann serverseitig
                // im Service automatisch auf den eingeloggten Nutzer gesetzt.
                // Nach dem Anlegen ist der Requester nicht mehr änderbar,
                // genau wie über die API.
                Select::make('requester_id')
                    ->label('Requester')
                    ->relationship('requester', 'name')
                    ->default(fn () => auth()->id())
                    ->visible(fn () => auth()->user()->hasRole('admin') || auth()->user()->hasRole('agent'))
                    ->disabled(fn (?Ticket $record) => $record !== null)
                    ->required(),
                Select::make('assignee_id')
                    ->label('Assignee')
                    ->relationship('assignee', 'name'),
                Select::make('asset_id')
                    ->label('Asset')
                    ->relationship('asset', 'name'),
                Select::make('category_id')
                    ->label('Category')
                    ->relationship('category', 'name'),
                TextInput::make('title')
                    ->required(),
                Textarea::make('description')
                    ->required()
                    ->columnSpanFull(),
                Select::make('status')
                    ->options(TicketStatus::class)
                    ->default(TicketStatus::Open)
                    ->required(),
                Select::make('priority')
                    ->options(TicketPriority::class)
                    ->default(TicketPriority::Medium)
                    ->required(),
                DateTimePicker::make('due_at'),
                // resolved_at/closed_at bewusst NICHT im Formular: Diese
                // Zeitstempel werden ausschließlich von TicketService::update()
                // anhand des tatsächlichen Statusübergangs gesetzt (siehe
                // EditTicket::handleRecordUpdate()). Frei editierbar könnte
                // hier jemand den historischen Lösungs-/Schließzeitpunkt
                // manuell verfälschen.
            ]);
    }
}
