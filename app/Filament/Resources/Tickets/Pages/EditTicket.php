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
     * Routes every change through TicketService::update() instead of
     * Filament's standard save logic — this is the only way the transition
     * matrix, the lock against concurrent access, and the audit trail also
     * remain effective in the panel, not just via the API.
     */
    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        // Filament's handleRecordUpdate() is generically typed with the
        // base Model class; but this page is bound exclusively to the
        // Ticket resource, $record is always a Ticket at runtime. assert()
        // gives PHPStan the type narrowing it needs for that.
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
