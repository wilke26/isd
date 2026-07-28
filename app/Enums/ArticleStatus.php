<?php

declare(strict_types=1);

namespace App\Enums;

enum ArticleStatus: string
{
    case Draft     = 'draft';
    case Published = 'published';
    case Archived  = 'archived';

    public function label(): string
    {
        return match($this) {
            self::Draft     => 'Entwurf',
            self::Published => 'Veröffentlicht',
            self::Archived  => 'Archiviert',
        };
    }
}
