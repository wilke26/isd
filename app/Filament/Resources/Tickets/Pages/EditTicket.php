<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tickets\Pages;

use App\Exceptions\InvalidTicketStatusTransitionException;
use App\Filament\Resources\Tickets\TicketResource;
use App\Models\Ticket;
use App\Services\TicketService;
use Filament\Actions\ViewAction;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\EditRecord;
use Filament\Support\Exceptions\Halt;
use Illuminate\Database\Eloquent\Model;

class EditTicket extends EditRecord
{
    protected static string $resource = TicketResource::class;

    protected function getHeaderActions(): array
    {
        return [
            ViewAction::make(),
        ];
    }

    /**
     * Leitet jede Änderung durch TicketService::update() statt Filaments
     * Standard-Speicherlogik — nur so bleiben Übergangsmatrix, Sperre gegen
     * Parallelzugriffe und Audit Trail auch im Panel wirksam, nicht nur
     * über die API.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Filaments handleRecordUpdate() ist generisch mit der Basisklasse
        // Model typisiert; diese Page ist aber ausschließlich an die
        // Ticket-Resource gebunden, $record ist zur Laufzeit immer ein
        // Ticket. assert() gibt PHPStan die dafür nötige Typ-Engführung.
        assert($record instanceof Ticket);

        try {
            return app(TicketService::class)->update($record, auth()->user(), $data);
        } catch (InvalidTicketStatusTransitionException $e) {
            Notification::make()
                ->danger()
                ->title('Ungültiger Statusübergang')
                ->body($e->getMessage())
                ->send();

            throw new Halt;
        }
    }
}
