<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\SmsSender;
use App\Domain\Notifications\DeliveryResult;
use Illuminate\Support\Facades\Log;

/**
 * Pilote SMS par défaut (SMS_PROVIDER=log, Q7) : consigne la remise dans le journal au lieu de l'envoyer.
 * Le journal est lisible par l'équipe technique : numéro masqué (quatre derniers chiffres), longueur seulement, jamais
 * le texte (code SOS, commune). En production, ce pilote ne remet rien : la remise est un échec (avertissement) pour
 * que les replis et le rejeu (P5) se déclenchent au lieu d'un SMS de SOS réputé envoyé.
 */
final class LogSmsSender implements SmsSender
{
    public function send(string $to, string $text): DeliveryResult
    {
        $context = ['to' => self::maskNumber($to), 'length' => mb_strlen($text)];

        if (app()->isProduction()) {
            Log::warning('[sms:log] Pilote log en production : SMS non envoyé.', $context);

            return DeliveryResult::failed('sms: log driver, not delivered');
        }

        Log::info('[sms:log] SMS consigné (aucun envoi).', $context);

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
