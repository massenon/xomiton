<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Contracts;

use App\Domain\Notifications\DeliveryResult;
use App\Domain\Notifications\PushMessage;
use App\Models\PushSubscription;

/**
 * Envoi d'une notification FCM (application Flutter, jeton d'appareil dans `push_subscriptions.endpoint`,
 * `kind = fcm`), ADR-25 : FCM HTTP v1 authentifié par un compte de service Firebase (FIREBASE_CREDENTIALS, fichier
 * JSON hors racine web), jamais par une clé serveur héritée.
 *
 * Implémentations : App\Infrastructure\Notifications\LogFcmSender (journal, pilote par défaut). Point d'extension :
 * une classe `FirebaseFcmSender` (paquet `kreait/firebase-php` ou `laravel-notification-channels/fcm`) qui envoie
 * `PushMessage::title()` / `body()` en notification et `PushMessage::data()` (`type`, `id`, `url`, `category`) en
 * données, sur le canal Android « SOS et alertes » pour les catégories `sos` / `alertes` (P15-08), et renvoie
 * `DeliveryResult::gone()` quand FCM signale un jeton invalide (UNREGISTERED) pour que le canal supprime l'abonnement.
 */
interface FcmSender
{
    public function send(PushSubscription $subscription, PushMessage $message): DeliveryResult;
}
