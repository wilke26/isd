<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

enum TicketStatus: string implements HasColor, HasLabel
{
    case Open = 'open';
    case InProgress = 'in_progress';
    case WaitingForRequester = 'waiting_for_requester';
    case Resolved = 'resolved';
    case Closed = 'closed';

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

    // ─── Filament-Interfaces ────────────────────────────────────────
    // Delegieren bewusst an die bestehenden label()/color()-Methoden,
    // statt sie zu duplizieren — Filament-Badges (Tabelle, Infolist)
    // übernehmen dadurch automatisch dieselben Bezeichnungen und Farben,
    // die auch sonst im Projekt für diesen Status gelten.

    public function getLabel(): string
    {
        return $this->label();
    }

    public function getColor(): string
    {
        return $this->color();
    }

    /**
     * Übergangsmatrix. Gilt einheitlich für alle Rollen — auch Admins nehmen
     * keinen Sonderweg, um nicht zwei parallele Geschäftsregeln zu erhalten.
     * Korrekturen außerhalb der Matrix sind bewusst kein Bestandteil des MVP
     * und müssten als eigener, protokollierter Vorgang erfolgen.
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
        // Ein "Wechsel" auf den bereits aktuellen Status ist kein Fehler,
        // sondern ein No-Op (z.B. wenn ein Update nur andere Felder ändert,
        // status aber unverändert mitgeschickt wird).
        if ($this === $target) {
            return true;
        }

        return in_array($target, $this->allowedTransitions(), true);
    }
}
