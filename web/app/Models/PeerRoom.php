<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\PeerRoomFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

/**
 * Salon de discussion entre pair·es (DATABASE_DICTIONARY.md §8 `peer_rooms`) : réglages du salon
 * (modération, anonymat, taille) ; les échanges vivent dans la conversation de groupe rattachée.
 */
class PeerRoom extends Model
{
    /** @use HasFactory<PeerRoomFactory> */
    use HasFactory;

    /** Taille d'un salon : bornes du prototype (admin/passerelles.php `chat`). */
    public const MIN_MEMBERS = 5;

    public const MAX_MEMBERS = 500;

    public const DEFAULT_MAX_MEMBERS = 50;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'description',
        'is_active',
        'is_moderated',
        'allow_anonymous',
        'max_members',
        'conversation_id',
        'created_by',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'is_moderated' => 'boolean',
            'allow_anonymous' => 'boolean',
            'max_members' => 'integer',
        ];
    }

    protected static function booted(): void
    {
        // Le slug est dérivé du nom à la création s'il n'est pas fourni, puis ne change plus (URL publique).
        static::creating(function (PeerRoom $room): void {
            if (empty($room->slug)) {
                $room->slug = static::uniqueSlug($room->name);
            }
        });
    }

    /**
     * Conversation de groupe qui porte les échanges du salon.
     *
     * @return BelongsTo<Conversation, $this>
     */
    public function conversation(): BelongsTo
    {
        return $this->belongsTo(Conversation::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @param  Builder<PeerRoom>  $query
     */
    public function scopeActive(Builder $query): void
    {
        $query->where('is_active', true);
    }

    /**
     * @param  Builder<PeerRoom>  $query
     */
    public function scopeModerated(Builder $query): void
    {
        $query->where('is_moderated', true);
    }

    /**
     * @param  Builder<PeerRoom>  $query
     */
    public function scopeWhereSlug(Builder $query, string $slug): void
    {
        $query->where('slug', $slug);
    }

    /** Nombre de membres encore présent·es dans la conversation du salon. */
    public function membersCount(): int
    {
        return $this->conversation?->participants()->active()->count() ?? 0;
    }

    public function isFull(): bool
    {
        return $this->membersCount() >= $this->max_members;
    }

    /** Un·e membre peut rejoindre : salon actif, conversation ouverte, place disponible. */
    public function acceptsNewMembers(): bool
    {
        return $this->is_active && $this->conversation_id !== null && ! $this->isFull();
    }

    /** Slug unique dérivé du nom : suffixe numérique en cas de collision. */
    public static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) !== '' ? Str::slug($name) : 'salon';
        $slug = $base;

        for ($i = 2; static::query()->where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
