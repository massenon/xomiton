<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * Réponse de l'équipe à une contribution d'un·e membre (P18-12, P18-14) : ressource proposée acceptée ou refusée (avec le
 * motif écrit pour elle), réponse à une suggestion, événement approuvé ou refusé dans la file d'approbation. Cloche et
 * push, catégorie « Système » ; jamais le nom de la personne de l'équipe qui a décidé. `$detail` : titre de la ressource
 * ou de l'événement, ou extrait de la réponse ; `$reason` : motif d'un refus ou texte de la réponse. P20-08 : décision de la
 * modération sur une conversation en BD proposée (publiée, refusée avec motif).
 */
final class TeamNotice extends XomitonNotification
{
    public const PROPOSAL_ACCEPTED = 'proposal_accepted';

    public const PROPOSAL_REFUSED = 'proposal_refused';

    public const SUGGESTION_REPLIED = 'suggestion_replied';

    public const EVENT_APPROVED = 'event_approved';

    public const EVENT_REFUSED = 'event_refused';

    /** P20-08 : création « conversation en BD » publiée ou refusée (motif) par la modération. */
    public const BD_PUBLISHED = 'bd_published';

    public const BD_REFUSED = 'bd_refused';

    /** @var list<string> */
    public const KINDS = [self::PROPOSAL_ACCEPTED, self::PROPOSAL_REFUSED, self::SUGGESTION_REPLIED, self::EVENT_APPROVED, self::EVENT_REFUSED, self::BD_PUBLISHED, self::BD_REFUSED];

    public function __construct(
        public readonly string $kind,
        public readonly string $detail = '',
        public readonly ?string $reason = null,
        public readonly ?string $url = null,
    ) {}

    public function category(): string
    {
        return $this->kind === self::EVENT_APPROVED || $this->kind === self::EVENT_REFUSED
            ? DatabaseNotification::CATEGORY_EVENTS
            : DatabaseNotification::CATEGORY_SYSTEM;
    }

    public function isSensitive(): bool
    {
        return false;
    }

    /**
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        $reason = $this->reason !== null && trim($this->reason) !== '' ? trim($this->reason) : (string) __('notifications.team.no_reason');

        return [
            'title' => (string) __('notifications.team.'.$this->kind.'.title'),
            'body' => (string) __('notifications.team.'.$this->kind.'.body', ['title' => $this->detail, 'reason' => $reason]),
            'url' => $this->url ?? self::urlTo('notifications', [], '/notifications'),
            'icon' => match ($this->kind) {
                self::PROPOSAL_ACCEPTED, self::EVENT_APPROVED, self::BD_PUBLISHED => 'check-circle-fill',
                self::PROPOSAL_REFUSED, self::EVENT_REFUSED, self::BD_REFUSED => 'x-circle-fill',
                default => 'chat-left-text-fill',
            },
        ];
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['decision' => $this->kind];
    }

    protected function pushType(): string
    {
        return 'team_notice';
    }
}
