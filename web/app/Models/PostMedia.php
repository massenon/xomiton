<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PostMediaFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Média d'une publication (image, vidéo ou audio).
 */
class PostMedia extends Model
{
    /** @use HasFactory<PostMediaFactory> */
    use HasFactory;

    public const KIND_IMAGE = 'image';

    public const KIND_VIDEO = 'video';

    public const KIND_AUDIO = 'audio';

    /**
     * @var list<string>
     */
    public const KINDS = [self::KIND_IMAGE, self::KIND_VIDEO, self::KIND_AUDIO];

    protected $table = 'post_media';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'post_id',
        'kind',
        'path',
        'alt',
        'width',
        'height',
        'size',
        'position',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'width' => 'integer',
            'height' => 'integer',
            'size' => 'integer',
            'position' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<Post, $this>
     */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * @param  Builder<PostMedia>  $query
     */
    public function scopeImages(Builder $query): void
    {
        $query->where('kind', self::KIND_IMAGE);
    }

    /**
     * @param  Builder<PostMedia>  $query
     */
    public function scopeOrdered(Builder $query): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function isImage(): bool
    {
        return $this->kind === self::KIND_IMAGE;
    }
}
