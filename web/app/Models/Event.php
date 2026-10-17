<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\ContentStatus;
use App\Enums\RsvpStatus;
use App\Models\Concerns\HasUlid;
use Database\Factories\EventFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Événement de la communauté (DATABASE_DICTIONARY.md §4 `events`).
 */
class Event extends Model
{
    /** @use HasFactory<EventFactory> */
    use HasFactory, HasUlid, SoftDeletes;

    /** Organisateur·rice affiché·e quand ni compte ni libellé libre ne sont renseignés. */
    public const DEFAULT_ORGANIZER_LABEL = 'Équipe Xomiton';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'ulid',
        'title',
        'slug',
        'category_id',
        'starts_at',
        'ends_at',
        'place',
        'is_online',
        'online_url',
        'description',
        'image_path',
        'organizer_id',
        'organizer_label',
        'group_id',
        'page_id',
        'going_count',
        'status',
        'reminder_sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'is_online' => 'boolean',
            'going_count' => 'integer',
            'status' => ContentStatus::class,
            'reminder_sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Category, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function organizer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'organizer_id');
    }

    /**
     * @return BelongsTo<Group, $this>
     */
    public function group(): BelongsTo
    {
        return $this->belongsTo(Group::class);
    }

    /**
     * @return BelongsTo<Page, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    /**
     * @return HasMany<EventRsvp, $this>
     */
    public function rsvps(): HasMany
    {
        return $this->hasMany(EventRsvp::class);
    }

    /**
     * Membres qui participent (statut `going`).
     *
     * @return BelongsToMany<User, $this>
     */
    public function attendees(): BelongsToMany
    {
        return $this->belongsToMany(User::class, 'event_rsvps')
            ->withPivot(['status', 'reminder_opt_in'])
            ->wherePivot('status', RsvpStatus::Going->value)
            ->withTimestamps();
    }

    /**
     * @param  Builder<Event>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', ContentStatus::Published);
    }

    /**
     * Événements à venir (ou en cours), du plus proche au plus lointain.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeUpcoming(Builder $query): void
    {
        $query->where(function (Builder $q): void {
            $q->where('starts_at', '>=', now())
                ->orWhere('ends_at', '>=', now());
        })->orderBy('starts_at')->orderBy('id');
    }

    /**
     * Événements terminés, du plus récent au plus ancien.
     *
     * @param  Builder<Event>  $query
     */
    public function scopePast(Builder $query): void
    {
        $query->where('starts_at', '<', now())
            ->where(function (Builder $q): void {
                $q->whereNull('ends_at')->orWhere('ends_at', '<', now());
            })
            ->orderByDesc('starts_at');
    }

    /**
     * @param  Builder<Event>  $query
     */
    public function scopeOnline(Builder $query): void
    {
        $query->where('is_online', true);
    }

    /**
     * Événements dont le rappel J-1 reste à envoyer.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeReminderDue(Builder $query): void
    {
        $query->published()
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addDay()]);
    }

    /**
     * Nom affiché de l'organisateur·rice : pseudo du compte ou libellé libre.
     */
    public function organizerLabel(): string
    {
        return $this->organizer?->pseudo ?? $this->organizer_label ?? (string) __(self::DEFAULT_ORGANIZER_LABEL);
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at->isFuture() || ($this->ends_at !== null && $this->ends_at->isFuture());
    }
}
