<?php

declare(strict_types=1);

namespace App\Enums;

use Filament\Support\Contracts\HasColor;
use Filament\Support\Contracts\HasLabel;

/**
 * Enum for the status of a knowledge base article.
 */
enum ArticleStatus: string implements HasColor, HasLabel
{
    case Draft = 'draft';
    case Submitted = 'submitted';
    case Published = 'published';
    case Archived = 'archived';

    /**
     * Returns the German-language label for the article status.
     */
    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Entwurf',
            self::Submitted => 'Zur Prüfung eingereicht',
            self::Published => 'Veröffentlicht',
            self::Archived => 'Archiviert',
        };
    }

    /**
     * Returns the color used to display the article status.
     */
    public function color(): string
    {
        return match ($this) {
            self::Draft => 'gray',
            self::Submitted => 'amber',
            self::Published => 'green',
            self::Archived => 'purple',
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
