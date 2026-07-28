<?php

declare(strict_types=1);

namespace App\Enums;

enum TicketStatus: string
{
    case Open       = 'open';
    case InProgress = 'in_progress';
    case Resolved   = 'resolved';
    case Closed     = 'closed';

    public function label(): string
    {
        return match($this) {
            self::Open       => 'Offen',
            self::InProgress => 'In Bearbeitung',
            self::Resolved   => 'Gelöst',
            self::Closed     => 'Geschlossen',
        };
    }

    public function color(): string
    {
        return match($this) {
            self::Open       => 'blue',
            self::InProgress => 'amber',
            self::Resolved   => 'green',
            self::Closed     => 'gray',
        };
    }
}
