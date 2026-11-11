<?php

declare(strict_types=1);

namespace App\Application\Notifications;

use App\Models\DatabaseNotification;
use App\Notifications\EventCreatedInMyGroup;
use App\Notifications\GroupJoinAccepted;
use App\Notifications\GroupJoinRequested;
use App\Notifications\InvitationAccepted;
use App\Notifications\InvitationReceived;
use Illuminate\Database\Eloquent\Builder;

/**
 * Filtres de la page /notifications (P8-10, prototype notifications.php l.8-9) : Toutes · Invitations · Événements ·
 * Échanges · Système. Ils regroupent les catégories stockées (`notifications.category`) et, pour la communauté, le
 * type de notification : invitations et adhésions → Invitations ; événement créé dans un groupe → Événements ; le
 * reste de la communauté et les messages → Échanges ; sécurité, SOS, alertes, modération et système → Système.
 * Une même règle sert le filtre SQL (apply) et le classement en PHP (of, compteurs) : les deux restent cohérents.
 */
enum NotificationTab: string
{
    case All = 'all';
    case Invitations = 'invitations';
    case Events = 'events';
    case Exchanges = 'exchanges';
    case System = 'system';

    /** Types (classe de la notification, colonne `type`) classés dans Invitations. */
    public const INVITATION_TYPES = [
        InvitationReceived::class,
        InvitationAccepted::class,
        GroupJoinRequested::class,
        GroupJoinAccepted::class,
    ];

    /** Types de la communauté classés dans Événements. */
    public const EVENT_TYPES = [EventCreatedInMyGroup::class];

    /** Catégories classées dans Échanges (hors types ci-dessus). */
    public const EXCHANGE_CATEGORIES = [DatabaseNotification::CATEGORY_MESSAGES, DatabaseNotification::CATEGORY_COMMUNITY];

    /** Catégories classées dans Événements. */
    public const EVENT_CATEGORIES = [DatabaseNotification::CATEGORY_EVENTS];

    /**
     * Filtres affichés, dans l'ordre du prototype.
     *
     * @return list<self>
     */
    public static function ordered(): array
    {
        return [self::All, self::Invitations, self::Events, self::Exchanges, self::System];
    }

    /**
     * @return list<string>
     */
    public static function values(): array
    {
        return array_map(static fn (self $tab): string => $tab->value, self::ordered());
    }

    /**
     * Filtre d'une notification (jamais `All`) : catégorie stockée et classe de la notification.
     */
    public static function of(?string $category, ?string $type): self
    {
        if (in_array($type, self::INVITATION_TYPES, true)) {
            return self::Invitations;
        }

        if (in_array($type, self::EVENT_TYPES, true) || in_array($category, self::EVENT_CATEGORIES, true)) {
            return self::Events;
        }

        if (in_array($category, self::EXCHANGE_CATEGORIES, true)) {
            return self::Exchanges;
        }

        return self::System;
    }

    /**
     * Restreint une requête sur `notifications` aux lignes de ce filtre (`All` : aucune restriction).
     *
     * @param  Builder<DatabaseNotification>  $query
     */
    public function apply(Builder $query): void
    {
        $special = [...self::INVITATION_TYPES, ...self::EVENT_TYPES];
        $named = [...self::EVENT_CATEGORIES, ...self::EXCHANGE_CATEGORIES];

        match ($this) {
            self::All => null,
            self::Invitations => $query->whereIn('type', self::INVITATION_TYPES),
            self::Events => $query->whereNotIn('type', self::INVITATION_TYPES)->where(static function (Builder $q): void {
                $q->whereIn('type', self::EVENT_TYPES)->orWhereIn('category', self::EVENT_CATEGORIES);
            }),
            self::Exchanges => $query->whereNotIn('type', $special)->whereIn('category', self::EXCHANGE_CATEGORIES),
            self::System => $query->whereNotIn('type', $special)->where(static function (Builder $q) use ($named): void {
                $q->whereNull('category')->orWhereNotIn('category', $named);
            }),
        };
    }
}
