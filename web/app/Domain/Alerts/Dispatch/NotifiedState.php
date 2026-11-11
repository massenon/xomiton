<?php

declare(strict_types=1);

namespace App\Domain\Alerts\Dispatch;

/**
 * État agrégé d'une personne prévenue (page /sos, `GET sos/{id}`, P5-03 ; maquette mobile « a vu l'alerte »,
 * « Envoyé · en attente », « SMS envoyé ») : la première règle vraie l'emporte, le SMS prime sur le push à l'affichage.
 */
enum NotifiedState: string
{
    case Seen = 'seen';
    case SmsSent = 'sms_sent';
    case Sent = 'sent';
    case Pending = 'pending';

    /**
     * @param  iterable<RowSnapshot>  $rows
     */
    public static function fromRows(iterable $rows): self
    {
        $sms = false;
        $sent = false;

        foreach ($rows as $row) {
            if ($row->seenAt !== null) {
                return self::Seen;
            }

            if ($row->status !== 'sent') {
                continue;
            }

            if ($row->channel === 'sms') {
                $sms = true;
            } elseif (in_array($row->channel, ['push', 'database', 'mail'], true)) {
                $sent = true;
            }
        }

        return match (true) {
            $sms => self::SmsSent,
            $sent => self::Sent,
            default => self::Pending,
        };
    }

    public function label(): string
    {
        return (string) __('alerts.notified.state.'.$this->value);
    }
}
