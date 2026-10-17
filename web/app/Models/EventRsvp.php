<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\RsvpStatus;
use Database\Factories\EventRsvpFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Réponse d'un·e membre à un événement (une seule par événement et par membre).
 */
class EventRsvp extends Model
{
    /** @use HasFactory<EventRsvpFactory> */
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'event_id',
        'user_id',
        'status',
        'reminder_opt_in',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => RsvpStatus::class,
            'reminder_opt_in' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<Event, $this>
     */
    public function event(): BelongsTo
    {
        return $this->belongsTo(Event::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @param  Builder<EventRsvp>  $query
     */
    public function scopeGoing(Builder $query): void
    {
        $query->where('status', RsvpStatus::Going);
    }

    /**
     * @param  Builder<EventRsvp>  $query
     */
    public function scopeInterested(Builder $query): void
    {
        $query->where('status', RsvpStatus::Interested);
    }

    /**
     * Participant·es à prévenir (participent et ont accepté le rappel).
     *
     * @param  Builder<EventRsvp>  $query
     */
    public function scopeWantsReminder(Builder $query): void
    {
        $query->going()->where('reminder_opt_in', true);
    }

    public function isGoing(): bool
    {
        return $this->status->countsAsGoing();
    }
}
