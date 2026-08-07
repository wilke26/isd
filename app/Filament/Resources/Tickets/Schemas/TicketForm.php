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
 * Form fields for creating/editing a ticket in the Filament panel.
 */
class TicketForm
{
    public static function configure(Schema $schema): Schema
    {
        return $schema
            ->components([
                // Only staff may choose a different requester when
                // creating a ticket (e.g. a problem reported by phone) —
                // non-staff don't see the field at all, requester_id is
                // then automatically set server-side in the service to the
                // logged-in user. Once created, the requester can no
                // longer be changed, exactly as via the API.
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
                // resolved_at/closed_at deliberately NOT in the form: these
                // timestamps are set exclusively by TicketService::update()
                // based on the actual status transition (see
                // EditTicket::handleRecordUpdate()). If freely editable,
                // someone could manually falsify the historical
                // resolved/closed time here.
            ]);
    }
}
