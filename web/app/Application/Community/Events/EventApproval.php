<?php

declare(strict_types=1);

namespace App\Application\Community\Events;

use App\Models\User;
use App\Support\Audit;
use App\Support\Settings;

/**
 * File d'approbation facultative des événements (P18-14) : réglage `events.require_approval` (table `settings`, coupé par
 * défaut pour ne rien changer au comportement existant). Activé, un événement créé par un·e membre reste « en attente »
 * (statut `en_attente`), invisible des autres membres, jusqu'à la décision de la modération dans `/admin/evenements`.
 * Le réglage se change dans cet écran avec `events.manage` (journal `settings.events_approval`).
 */
final class EventApproval
{
    public const SETTING = 'events.require_approval';

    public static function required(): bool
    {
        return Settings::bool(self::SETTING, false);
    }

    public static function set(bool $required, User $actor): void
    {
        Settings::set(self::SETTING, $required, (int) $actor->getKey());
        Audit::log('settings.events_approval', null, $required ? 'on' : 'off', $actor);
    }
}
