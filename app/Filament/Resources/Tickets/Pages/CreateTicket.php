<?php

declare(strict_types=1);

namespace App\Filament\Resources\Tickets\Pages;

use App\Filament\Resources\Tickets\TicketResource;
use App\Services\TicketService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

class CreateTicket extends CreateRecord
{
    protected static string $resource = TicketResource::class;

    /**
     * Leitet die Erstellung durch TicketService statt Filaments
     * Standard-Speicherlogik (rohes Eloquent-create()) — sorgt dafür, dass
     * der initiale History-Eintrag ("open") genauso entsteht wie über die
     * API, unabhängig vom Eingabeweg.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $requesterId = $data['requester_id'] ?? null;
        unset($data['requester_id']);

        return app(TicketService::class)->create(auth()->user(), $data, $requesterId);
    }
}
