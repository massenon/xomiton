<?php

declare(strict_types=1);

namespace App\Notifications\Channels;

use App\Notifications\XomitonNotification;
use Illuminate\Notifications\Channels\DatabaseChannel;
use Illuminate\Notifications\Notification;

/**
 * Canal `database` de Xomiton : le canal Laravel ne remplit que `data` ; celui-ci renseigne aussi les colonnes
 * `notifications.category` et `notifications.is_sensitive` (DATABASE_DICTIONARY.md §9) à l'insertion, pour toute
 * notification héritant de XomitonNotification. Lié à la place de DatabaseChannel dans AppServiceProvider.
 */
final class XomitonDatabaseChannel extends DatabaseChannel
{
    /**
     * @param  mixed  $notifiable
     * @return array<string, mixed>
     */
    protected function buildPayload($notifiable, Notification $notification): array
    {
        $payload = parent::buildPayload($notifiable, $notification);

        if ($notification instanceof XomitonNotification) {
            $payload['category'] = $notification->category();
            $payload['is_sensitive'] = $notification->isSensitive();
        }

        return $payload;
    }
}
