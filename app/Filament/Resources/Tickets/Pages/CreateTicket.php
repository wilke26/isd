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
     * Routes creation through TicketService instead of Filament's standard
     * save logic (raw Eloquent create()) — ensures the initial history
     * entry ("open") is created the same way as via the API, regardless of
     * the entry path.
     */
    protected function handleRecordCreation(array $data): Model
    {
        $requesterId = $data['requester_id'] ?? null;
        unset($data['requester_id']);

        return app(TicketService::class)->create(auth()->user(), $data, $requesterId);
    }
}
