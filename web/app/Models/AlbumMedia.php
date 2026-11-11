<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\AlbumMediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Média d'un album de profil (image ou vidéo), DATABASE_DICTIONARY.md §4 `album_media`.
 */
class AlbumMedia extends Model
{
    /** @use HasFactory<AlbumMediaFactory> */
    use HasFactory;

    public const KIND_IMAGE = 'image';

    public const KIND_VIDEO = 'video';

    /** @var list<string> */
    public const KINDS = [self::KIND_IMAGE, self::KIND_VIDEO];

    protected $table = 'album_media';

    /** @var list<string> */
    protected $fillable = [
        'album_id',
        'path',
        'kind',
        'alt',
        'width',
        'height',
        'size',
        'position',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
            'position' => 'integer',
        ];
    }

    /** @return BelongsTo<Album, $this> */
    public function album(): BelongsTo
    {
        return $this->belongsTo(Album::class);
    }

    /** @param  Builder<AlbumMedia>  $query */
    public function scopeImages(Builder $query): void
    {
        $query->where('kind', self::KIND_IMAGE);
    }

    /** @param  Builder<AlbumMedia>  $query */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function isImage(): bool
    {
        return $this->kind === self::KIND_IMAGE;
    }
}
