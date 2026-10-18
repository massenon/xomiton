<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\DeliveryResult;
use Illuminate\Support\Facades\Log;

/**
 * Pilote SMS par défaut (SMS_PROVIDER=log, Q7) : consigne le message dans le journal au lieu de l'envoyer.
 * Le numéro est masqué (quatre derniers chiffres) : le journal est lisible par l'équipe technique.
 */
final class LogSmsSender implements SmsSender
{
    public function send(string $to, string $text): DeliveryResult
    {
        Log::info('[sms:log] SMS consigné (aucun envoi).', [
            'to' => self::maskNumber($to),
            'length' => mb_strlen($text),
            'text' => $text,
        ]);

        return DeliveryResult::sent();
    }

    public static function maskNumber(string $number): string
    {
        $digits = preg_replace('/\D+/', '', $number) ?? '';

        if (strlen($digits) <= 4) {
            return '****';
        }

        return str_repeat('*', strlen($digits) - 4).substr($digits, -4);
    }
}
