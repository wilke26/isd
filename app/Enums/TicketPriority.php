<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Enum für die Prioritätsstufen eines Tickets.
 */
enum TicketPriority: string implements HasColor, HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Gibt die deutsche Bezeichnung der Priorität zurück.
     */
    public function label(): string
    {
        return match ($this) {
            self::Low => 'Niedrig',
            self::Medium => 'Mittel',
            self::High => 'Hoch',
            self::Critical => 'Kritisch',
        };
    }

    /**
     * Gibt die Farbe für die Darstellung der Priorität zurück.
     */
    public function color(): string
    {
        return match ($this) {
            self::Low => 'gray',
            self::Medium => 'blue',
            self::High => 'amber',
            self::Critical => 'red',
        };
    }

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }
}
