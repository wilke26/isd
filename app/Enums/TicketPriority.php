<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Enum for the priority levels of a ticket.
 */
enum TicketPriority: string implements HasColor, HasLabel
{
    case Low = 'low';
    case Medium = 'medium';
    case High = 'high';
    case Critical = 'critical';

    /**
     * Returns the German-language label for the priority.
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
     * Returns the color used to display the priority.
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
