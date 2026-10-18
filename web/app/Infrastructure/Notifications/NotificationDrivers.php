<?php

declare(strict_types=1);

namespace App\Infrastructure\Notifications;

use App\Domain\Notifications\Contracts\FcmSender;
use App\Domain\Notifications\Contracts\PushSender;
use App\Domain\Notifications\Contracts\SmsSender;
use Illuminate\Contracts\Container\Container;
use Illuminate\Support\Facades\Log;

/**
 * Résout le pilote de chaque canal externe (ADR-25, Q7) à partir de `config/xomiton.php` :
 * `notifications.push.webpush.driver`, `notifications.push.fcm.driver`, `sms.provider`.
 *
 * Point d'extension : ajouter la classe du pilote dans la table correspondante (elle implémente l'interface du
 * domaine) et choisir son nom dans `.env`. Un nom inconnu, ou une classe absente parce que le paquet n'est pas
 * installé, retombe sur `log` avec un avertissement : un SOS ne doit jamais échouer sur une erreur de configuration.
 */
final class NotificationDrivers
{
    public const LOG = 'log';

    /** @var array<string, class-string<PushSender>|string> */
    public const WEBPUSH = [
        self::LOG => LogPushSender::class,
        // `composer require minishlink/web-push` puis implémenter cette classe (voir PushSender).
        'vapid' => 'App\\Infrastructure\\Notifications\\VapidPushSender',
    ];

    /** @var array<string, class-string<FcmSender>|string> */
    public const FCM = [
        self::LOG => LogFcmSender::class,
        // Paquet FCM HTTP v1 + FIREBASE_CREDENTIALS, puis implémenter cette classe (voir FcmSender).
        'firebase' => 'App\\Infrastructure\\Notifications\\FirebaseFcmSender',
    ];

    /** @var array<string, class-string<SmsSender>|string> */
    public const SMS = [
        self::LOG => LogSmsSender::class,
    ];

    private function __construct() {}

    public static function webPush(Container $app): PushSender
    {
        /** @var PushSender $sender */
        $sender = self::resolve($app, 'webpush', (string) config('xomiton.notifications.push.webpush.driver', self::LOG), self::WEBPUSH);

        return $sender;
    }

    public static function fcm(Container $app): FcmSender
    {
        /** @var FcmSender $sender */
        $sender = self::resolve($app, 'fcm', (string) config('xomiton.notifications.push.fcm.driver', self::LOG), self::FCM);

        return $sender;
    }

    public static function sms(Container $app): SmsSender
    {
        /** @var SmsSender $sender */
        $sender = self::resolve($app, 'sms', (string) config('xomiton.sms.provider', self::LOG), self::SMS);

        return $sender;
    }

    /**
     * Nom effectif du pilote (après repli éventuel), pour l'affichage en administration et les tests.
     *
     * @param  array<string, string>  $drivers
     */
    public static function effective(string $channel, string $driver, array $drivers): string
    {
        $driver = strtolower(trim($driver));

        if (! isset($drivers[$driver])) {
            Log::warning(sprintf('Notifications : pilote « %s » inconnu pour le canal %s, repli sur log.', $driver, $channel));

            return self::LOG;
        }

        if (! class_exists($drivers[$driver])) {
            Log::warning(sprintf('Notifications : pilote « %s » du canal %s non installé (%s), repli sur log.', $driver, $channel, $drivers[$driver]));

            return self::LOG;
        }

        return $driver;
    }

    /**
     * @param  array<string, string>  $drivers
     */
    private static function resolve(Container $app, string $channel, string $driver, array $drivers): object
    {
        $effective = self::effective($channel, $driver, $drivers);

        return $app->make($drivers[$effective]);
    }
}
