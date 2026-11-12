<?php

declare(strict_types=1);

namespace App\Notifications;

use App\Models\DatabaseNotification;

/**
 * Décision de l'équipe concernant le compte ou un contenu de la personne (P12-01, P12-06, NOTIFICATIONS §3) : avertissement,
 * contenu masqué, compte suspendu (P18-06 : éventuellement jusqu'à une date), banni ou réactivé, inscription approuvée. Cloche seulement (aucun modèle d'e-mail dans le
 * catalogue : à ajouter en P14, plan Q91) ; catégorie sécurité du compte, donc non désactivable. Jamais l'extrait du
 * contenu ni le nom de la personne qui a décidé ; le motif d'un avertissement ou d'une suspension est écrit pour la
 * personne elle-même (200 caractères). Sensible quand il touche un contenu : texte neutre sur écran verrouillé.
 */
final class ModerationNotice extends XomitonNotification
{
    public const WARNING = 'warning';

    public const CONTENT_HIDDEN = 'content_hidden';

    public const SUSPENDED = 'suspended';

    /** P18-06 : suspension temporaire, avec sa date de fin. */
    public const SUSPENDED_UNTIL = 'suspended_until';

    /** P18-06 (Q97) : compte banni. */
    public const BANNED = 'banned';

    public const REACTIVATED = 'reactivated';

    public const APPROVED = 'approved';

    /** @var list<string> */
    public const KINDS = [self::WARNING, self::CONTENT_HIDDEN, self::SUSPENDED, self::SUSPENDED_UNTIL, self::BANNED, self::REACTIVATED, self::APPROVED];

    public function __construct(
        public readonly string $kind,
        public readonly ?string $reason = null,
        public readonly ?int $reportId = null,
        public readonly int $warningCount = 0,
        public readonly ?string $until = null,
    ) {}

    public function category(): string
    {
        return DatabaseNotification::CATEGORY_SECURITY;
    }

    public function isSensitive(): bool
    {
        return in_array($this->kind, [self::WARNING, self::CONTENT_HIDDEN], true);
    }

    protected function wantsPush(): bool
    {
        return false;
    }

    /**
     * @return array{title: string, body: string, url: string, icon: string}
     */
    protected function content(object $notifiable): array
    {
        $reason = $this->reason !== null && trim($this->reason) !== '' ? trim($this->reason) : (string) __('notifications.moderation.no_reason');

        return [
            'title' => (string) __('notifications.moderation.'.$this->kind.'.title'),
            'body' => (string) __('notifications.moderation.'.$this->kind.'.body', ['reason' => $reason, 'count' => $this->warningCount, 'date' => (string) $this->until]),
            'url' => $this->kind === self::APPROVED ? self::urlTo('fil', [], '/fil') : self::urlTo('parametres', [], '/parametres'),
            'icon' => match ($this->kind) {
                self::WARNING => 'exclamation-triangle-fill',
                self::CONTENT_HIDDEN => 'eye-slash-fill',
                self::SUSPENDED, self::SUSPENDED_UNTIL => 'pause-circle-fill',
                self::BANNED => 'slash-circle-fill',
                default => 'patch-check-fill',
            },
        ];
    }

    /**
     * @return array{title: string, body: string}
     */
    protected function discreetContent(object $notifiable): array
    {
        return self::discreetFrom('notifications.moderation.discreet');
    }

    /**
     * @return array<string, scalar|null>
     */
    protected function extra(object $notifiable): array
    {
        return ['decision' => $this->kind, 'report_id' => $this->reportId];
    }

    protected function subjectId(): int|string|null
    {
        return $this->reportId;
    }

    protected function pushType(): string
    {
        return 'moderation_notice';
    }
}
