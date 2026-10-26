<?php

declare(strict_types=1);

namespace App\Models;

use Database\Factories\MailOutboxFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * E-mail en attente d'envoi (DATABASE_DICTIONARY.md §9 `mail_outbox`) : déposé quand aucun SMTP n'est
 * configuré, puis expédié par `mail:flush-outbox` dès qu'un transport est disponible.
 */
class MailOutbox extends Model
{
    /** @use HasFactory<MailOutboxFactory> */
    use HasFactory;

    /** Au-delà, l'e-mail reste en file mais n'est plus retenté automatiquement. */
    public const MAX_ATTEMPTS = 5;

    protected $table = 'mail_outbox';

    /**
     * Jamais sérialisés (JSON, Livewire, journaux) : en-têtes et corps portent le lien de désinscription, le pixel
     * d'ouverture et les liens de suivi signés de la personne.
     *
     * @var list<string>
     */
    protected $hidden = ['headers', 'html', 'text'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'to',
        'from_address',
        'from_name',
        'reply_to',
        'subject',
        'html',
        'text',
        'template_slug',
        'headers',
        'newsletter_recipient_id',
        'attempts',
        'sent_at',
        'last_error',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'headers' => 'array',
            'attempts' => 'integer',
            'sent_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<EmailTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_slug', 'slug');
    }

    /**
     * @param  Builder<MailOutbox>  $query
     */
    public function scopePending(Builder $query): void
    {
        $query->whereNull('sent_at');
    }

    /**
     * @param  Builder<MailOutbox>  $query
     */
    public function scopeSent(Builder $query): void
    {
        $query->whereNotNull('sent_at');
    }

    /**
     * En attente et encore retentables : traités dans l'ordre de dépôt par `mail:flush-outbox`.
     *
     * @param  Builder<MailOutbox>  $query
     */
    public function scopeFlushable(Builder $query): void
    {
        $query->pending()->where('attempts', '<', self::MAX_ATTEMPTS)->orderBy('id');
    }

    public function isSent(): bool
    {
        return $this->sent_at !== null;
    }

    public function canRetry(): bool
    {
        return ! $this->isSent() && $this->attempts < self::MAX_ATTEMPTS;
    }

    public function markSent(): void
    {
        $this->forceFill(['sent_at' => now(), 'last_error' => null])->save();
    }

    public function markFailed(string $error): void
    {
        $this->forceFill([
            'attempts' => $this->attempts + 1,
            'last_error' => mb_substr($error, 0, 255),
        ])->save();
    }
}
