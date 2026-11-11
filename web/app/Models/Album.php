<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ProfileVisibility;
use App\Models\Concerns\HasUlid;
use Database\Factories\AlbumFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Album de la médiathèque d'un profil (DATABASE_DICTIONARY.md §4 `albums`). La visibilité reprend
 * les quatre niveaux du profil (`ProfileVisibility`) : le dictionnaire cite `PostVisibility`, qui ne
 * connaît pas la valeur par défaut `community` ; l'écart est consigné dans le dictionnaire.
 */
class Album extends Model
{
    /** @use HasFactory<AlbumFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    public const KIND_PROFILE = 'profile';

    public const KIND_COVER = 'cover';

    public const KIND_CUSTOM = 'custom';

    /** @var list<string> */
    public const KINDS = [self::KIND_PROFILE, self::KIND_COVER, self::KIND_CUSTOM];

    /** @var list<string> */
    protected $fillable = [
        'ulid',
        'user_id',
        'kind',
        'title',
        'description',
        'visibility',
        'media_count',
        'cover_media_id',
    ];

    /** @return array<string, string> */
    protected function casts(): array
    {
        return [
            'visibility' => ProfileVisibility::class,
            'media_count' => 'integer',
            'cover_media_id' => 'integer',
        ];
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<AlbumMedia, $this> */
    public function media(): HasMany
    {
        return $this->hasMany(AlbumMedia::class)->orderBy('position')->orderBy('id');
    }

    /**
     * Média de couverture choisi ; à défaut, le premier média de l'album (voir `coverMedia()`).
     *
     * @return BelongsTo<AlbumMedia, $this>
     */
    public function cover(): BelongsTo
    {
        return $this->belongsTo(AlbumMedia::class, 'cover_media_id');
    }

    public function coverMedia(): ?AlbumMedia
    {
        return $this->cover ?? $this->media()->first();
    }

    /** @param  Builder<Album>  $query */
    public function scopeOfKind(Builder $query, string $kind): void
    {
        $query->where('kind', $kind);
    }

    /** @param  Builder<Album>  $query */
    public function scopeCustom(Builder $query): void
    {
        $query->where('kind', self::KIND_CUSTOM);
    }

    /**
     * Albums lisibles par un·e membre (ou un·e visiteur·se si null) : publics ; pour un·e membre connecté·e,
     * aussi ceux de la communauté, les siens, et ceux réservés aux abonné·es d'un·e propriétaire suivi·e.
     *
     * @param  Builder<Album>  $query
     */
    public function scopeVisibleTo(Builder $query, ?User $viewer): void
    {
        $query->where(function (Builder $q) use ($viewer): void {
            $q->where('visibility', ProfileVisibility::Public);

            if ($viewer === null) {
                return;
            }

            $q->orWhere('visibility', ProfileVisibility::Community)
                ->orWhere('user_id', $viewer->id)
                ->orWhere(function (Builder $r) use ($viewer): void {
                    $r->where('visibility', ProfileVisibility::Followers)
                        ->whereIn('user_id', Follow::query()->select('followed_id')->where('follower_id', $viewer->id));
                });
        });
    }

    /**
     * Ordre d'affichage de la médiathèque : les plus récents d'abord.
     *
     * @param  Builder<Album>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isSystem(): bool
    {
        return $this->kind !== self::KIND_CUSTOM;
    }

    /** Recalcule le compteur dénormalisé après ajout ou retrait d'un média. */
    public function syncMediaCount(): void
    {
        $this->forceFill(['media_count' => $this->media()->count()])->saveQuietly();
    }
}
