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

    /** Visuel d'un événement créé sans image (prototype events.php : `hero-05.jpg`). */
    public const DEFAULT_COVER = 'assets/images/xomiton/hero-05.jpg';

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
        'address',
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
        'is_public',
        'reminder_sent_at',
    ];

    /**
     * Adresse exacte et lien de connexion (P8-04, Q77) : chiffrés au repos et jamais sérialisés par défaut — seules les
     * ressources de l'API les servent, aux participant·es confirmé·es, à l'organisateur·rice et à la modération
     * (App\Application\Community\Events\EventAccess).
     *
     * @var list<string>
     */
    protected $hidden = ['address', 'online_url'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'starts_at' => 'datetime',
            'ends_at' => 'datetime',
            'address' => 'encrypted',
            'online_url' => 'encrypted',
            'is_online' => 'boolean',
            'is_public' => 'boolean',
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
     * Événements dont le rappel de la veille reste à envoyer : publiés, commençant dans les `$hours` heures.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeReminderDue(Builder $query, int $hours = 24): void
    {
        $query->published()
            ->whereNull('reminder_sent_at')
            ->whereBetween('starts_at', [now(), now()->addHours(max(1, $hours))]);
    }

    /**
     * Le rappel de la veille peut encore partir (il n'est pas envoyé et l'événement n'a pas commencé) : une réponse
     * « Je participe » donnée maintenant le recevra.
     */
    public function reminderAvailable(): bool
    {
        return $this->reminder_sent_at === null && $this->starts_at->isFuture();
    }

    /**
     * Nom affiché de l'organisateur·rice aux membres : pseudo du compte ou libellé libre.
     */
    public function organizerLabel(): string
    {
        return $this->organizer?->pseudo ?? $this->organizer_label ?? (string) __(self::DEFAULT_ORGANIZER_LABEL);
    }

    /**
     * Organisation affichée sur la page publique indexée (Q63, Q77) : nom de la page ou du groupe, sinon « Équipe
     * Xomiton » — **jamais le pseudo d'un compte ni un libellé libre** (qui peut nommer une personne), comme le JSON-LD
     * (`#org`).
     */
    public function publicOrganizerLabel(): string
    {
        $page = $this->page_id !== null ? $this->page : null;
        $group = $this->group_id !== null ? $this->group : null;

        return match (true) {
            $page !== null && trim((string) $page->name) !== '' => (string) $page->name,
            $group !== null && trim((string) $group->name) !== '' => (string) $group->name,
            default => (string) __(self::DEFAULT_ORGANIZER_LABEL),
        };
    }

    public function isPublished(): bool
    {
        return $this->status === ContentStatus::Published;
    }

    /**
     * Ouvert aux visiteur·ses (Q35 a) : drapeau `is_public` sur un événement publié.
     *
     * @param  Builder<Event>  $query
     */
    public function scopeOpenToPublic(Builder $query): void
    {
        $query->published()->where('is_public', true);
    }

    public function isPast(): bool
    {
        return ! $this->isUpcoming();
    }

    public function isUpcoming(): bool
    {
        return $this->starts_at->isFuture() || ($this->ends_at !== null && $this->ends_at->isFuture());
    }
}
