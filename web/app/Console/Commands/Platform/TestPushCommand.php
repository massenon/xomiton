<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Domain\Notifications\PushMessage;
use App\Infrastructure\Notifications\NotificationDrivers;
use App\Models\PushSubscription;
use Illuminate\Console\Command;

/**
 * Essai d'envoi FCM (recette, mise en production) : une notification générique « test » vers UN jeton d'appareil fourni
 * par l'opérateur (celui de son propre téléphone), sans aucune donnée réelle. Jeton jamais affiché ni journalisé.
 */
class TestPushCommand extends Command
{
    protected $signature = 'notifications:test-push
        {token : Jeton FCM de l’appareil de test (copié depuis un appareil de recette)}
        {--urgent : Priorité haute, canal « SOS et alertes »}
        {--yes : Sans confirmation}';

    protected $description = 'Envoie une notification générique de test au jeton FCM donné, avec le pilote configuré (aucune donnée réelle)';

    public function handle(): int
    {
        $driver = (string) config('xomiton.notifications.push.fcm.driver', 'log');
        $this->line('Pilote FCM configuré : '.$driver.' (effectif : '.NotificationDrivers::effective('fcm', $driver, NotificationDrivers::FCM).')');

        if (! $this->option('yes') && ! $this->confirm('Envoyer une notification de test à cet appareil ?', false)) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }

        $category = $this->option('urgent') ? 'alertes' : 'systeme';
        $message = new PushMessage(
            title: 'Xomiton',
            body: 'Notification de test.',
            url: null,
            data: ['type' => 'test', 'id' => null, 'url' => null, 'category' => $category],
        );
        $subscription = new PushSubscription(['kind' => 'fcm', 'endpoint' => (string) $this->argument('token')]);

        $result = NotificationDrivers::fcm($this->laravel)->send($subscription, $message);

        if ($result->ok) {
            $this->info('Envoi accepté par le pilote.');

            return self::SUCCESS;
        }

        $this->error(($result->gone ? 'Jeton invalide ou désinscrit : ' : 'Échec : ').($result->error ?? 'inconnu'));

        return self::FAILURE;
    }
}
