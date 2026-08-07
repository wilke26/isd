<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Enum for the various statuses of a ticket.
 * Implements Filament interfaces for display in the UI.
 */
enum TicketStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingForRequester = 'waiting_for_requester';
    case Resolved = 'resolved';
    case Closed = 'closed';

    /**
     * Returns the German-language label for the status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Open => 'Offen',
            self::InProgress => 'In Bearbeitung',
            self::WaitingForRequester => 'Wartet auf Rückmeldung',
            self::Resolved => 'Gelöst',
            self::Closed => 'Geschlossen',
        };
    }

    /**
     * Returns the color used to display the status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Open => 'blue',
            self::InProgress => 'amber',
            self::WaitingForRequester => 'purple',
            self::Resolved => 'green',
            self::Closed => 'gray',
        };
    }

    // ─── Filament interfaces ────────────────────────────────────────
    // Deliberately delegate to the existing label()/color() methods
    // instead of duplicating them — this way Filament badges (table,
    // infolist) automatically pick up the same labels and colors used
    // for this status elsewhere in the project.

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }

    /**
     * Transition matrix. Applies uniformly to all roles — even admins get
     * no special path, so as to avoid ending up with two parallel sets of
     * business rules. Corrections outside the matrix are deliberately not
     * part of the MVP and would need to happen as a separate, logged
     * operation.
     *
     * @return list<self>
     */
    public function allowedTransitions(): array
    {
        return match ($this) {
            self::Open => [self::InProgress, self::Resolved],
            self::InProgress => [self::WaitingForRequester, self::Resolved],
            self::WaitingForRequester => [self::InProgress, self::Resolved],
            self::Resolved => [self::InProgress, self::Closed],
            self::Closed => [],
        };
    }

    public function canTransitionTo(self $target): bool
    {
        // A "change" to the already-current status is not an error but a
        // no-op (e.g. when an update only changes other fields but still
        // sends the unchanged status along).
        if ($this === $target) {
            return true;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }
}
