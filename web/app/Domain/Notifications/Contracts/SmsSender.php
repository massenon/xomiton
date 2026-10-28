<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\DeliveryResult;

/**
 * SMS de secours (NOTIFICATIONS_INSTRUCTION.md §1 : SOS uniquement, si le push échoue ; Q7 : fournisseur à choisir).
 * `config('xomiton.sms.driver')` désigne le pilote : `log` (App\Infrastructure\Notifications\LogSmsSender) tant
 * qu'aucun fournisseur n'est retenu. Point d'extension : une classe par fournisseur qui lit `xomiton.sms.api_key` et
 * `xomiton.sms.sender`, reçoit un texte déjà borné en segments GSM-7 (XomitonNotification::toSms,
 * `xomiton.sms.max_segments*`) et renvoie `DeliveryResult::failed()` avec le message d'erreur du fournisseur (rejoué
 * par `notifications:retry`). Un pilote HTTP implémente PoolableSmsSender (envoi borné par DeliveryRunner) ou applique
 * lui-même `xomiton.alerts.provider_timeout_seconds` à sa requête : un `send()` bloquant n'est pas interrompu (D13).
 */
interface SmsSender
{
    /**
     * @param  string  $to  numéro au format international (+229 01 XX XX XX XX)
     */
    public function send(string $to, string $text): DeliveryResult;
}
