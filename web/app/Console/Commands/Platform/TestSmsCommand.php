<?php

declare(strict_types=1);

namespace App\Console\Commands\Platform;

use App\Domain\Notifications\PhoneNumber;
use App\Infrastructure\Notifications\NotificationDrivers;
use Illuminate\Console\Command;

/**
 * Essai d'envoi SMS (recette, mise en production) : un texte fixe sans donnée réelle vers un numéro de l'opérateur.
 * Le numéro n'est jamais affiché ni journalisé en clair (empreinte).
 */
class TestSmsCommand extends Command
{
    protected $signature = 'notifications:test-sms
        {to : Numéro de test (E.164 ou local béninois ; +229 par défaut)}
        {--yes : Sans confirmation}';

    protected $description = 'Envoie un SMS de test fixe au numéro donné, avec le pilote configuré (aucune donnée réelle)';

    public function handle(): int
    {
        $driver = (string) config('xomiton.sms.driver', 'log');
        $to = PhoneNumber::e164((string) $this->argument('to'));

        if ($to === null) {
            $this->error('Numéro invalide (format E.164 attendu, par exemple +229…).');

            return self::INVALID;
        }

        $this->line('Pilote SMS configuré : '.$driver.' (effectif : '.NotificationDrivers::effective('sms', $driver, NotificationDrivers::SMS).') ; destinataire : empreinte '.PhoneNumber::fingerprint($to));

        if (! $this->option('yes') && ! $this->confirm('Envoyer un SMS de test (facturé par le fournisseur) ?', false)) {
            $this->warn('Annulé.');

            return self::FAILURE;
        }

        $result = NotificationDrivers::sms($this->laravel)->send($to, 'Xomiton : SMS de test.');

        if ($result->ok) {
            $this->info('Envoi accepté par le pilote.');

            return self::SUCCESS;
        }

        $this->error('Échec : '.($result->error ?? 'inconnu'));

        return self::FAILURE;
    }
}
