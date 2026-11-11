<?php

declare(strict_types=1);

namespace App\Models;

use App\Enums\MailTransport;
use Database\Factories\MailLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Trace d'un envoi d'e-mail (DATABASE_DICTIONARY.md §9 `mail_logs`) : une ligne par tentative, jamais
 * modifiée, purgée après 180 jours par la tâche planifiée de rétention.
 */
class MailLog extends Model
{
    /** @use HasFactory<MailLogFactory> */
    use HasFactory;

    public const UPDATED_AT = null;

    /** Rétention des journaux d'envoi (DATABASE_ENGINEERING.md §6). */
    public const RETENTION_DAYS = 180;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'to',
        'subject',
        'template_slug',
        'context',
        'transport',
        'ok',
        'error',
        'message_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'transport' => MailTransport::class,
            'ok' => 'boolean',
            'created_at' => 'datetime',
        ];
    }

    /**
     * Template utilisé, s'il existe encore.
     *
     * @return BelongsTo<EmailTemplate, $this>
     */
    public function template(): BelongsTo
    {
        return $this->belongsTo(EmailTemplate::class, 'template_slug', 'slug');
    }

    /**
     * @param  Builder<MailLog>  $query
     */
    public function scopeSuccessful(Builder $query): void
    {
        $query->where('ok', true);
    }

    /**
     * @param  Builder<MailLog>  $query
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('ok', false);
    }

    /**
     * @param  Builder<MailLog>  $query
     */
    public function scopeForTemplate(Builder $query, string $slug): void
    {
        $query->where('template_slug', $slug);
    }

    /**
     * @param  Builder<MailLog>  $query
     */
    public function scopeViaTransport(Builder $query, MailTransport $transport): void
    {
        $query->where('transport', $transport->value);
    }

    /**
     * Lignes au-delà de la rétention : supprimées par la tâche planifiée.
     *
     * @param  Builder<MailLog>  $query
     */
    public function scopeExpired(Builder $query): void
    {
        $query->where('created_at', '<', now()->subDays(self::RETENTION_DAYS));
    }

    /**
     * @param  Builder<MailLog>  $query
     */
    public function scopeLatestFirst(Builder $query): void
    {
        $query->orderByDesc('created_at')->orderByDesc('id');
    }

    public function isSuccessful(): bool
    {
        return $this->ok;
    }
}
