<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ArticleStatus;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Repräsentiert einen Artikel in der Wissensdatenbank (Knowledge Base).
 *
 * @mixin IdeHelperKbArticle
 */
class KbArticle extends Model
{
    use HasFactory, SoftDeletes;

    /**
     * Die Attribute, die massenzuweisbar sind.
     *
     * @var list<string>
     */
    protected $fillable = [
        'author_id',
        'category_id',
        'title',
        'slug',
        'body',
        'status',
        'published_at',
    ];

    /**
     * Die Attribute, die konvertiert werden sollen.
     */
    protected function casts(): array
    {
        return [
            'status' => ArticleStatus::class,
            'published_at' => 'datetime',
        ];
    }

    /**
     * Gibt den Autor des Artikels zurück.
     */
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class, 'author_id');
    }

    /**
     * Gibt die Kategorie des Artikels zurück.
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(KbCategory::class);
    }

    /**
     * Gibt die Schlagwörter (Tags) des Artikels zurück.
     */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class, 'kb_article_tag');
    }

    /**
     * Prüft, ob der Artikel veröffentlicht ist.
     */
    public function isPublished(): bool
    {
        return $this->status === ArticleStatus::Published;
    }
}
