<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Schedule;

/*
 * Planificateur (SOFTWARE_ARCHITECTURE.md §6, D7 : cPanel mutualisé, une seule ligne cron
 * « * * * * * php artisan schedule:run », aucun processus permanent). Contraintes :
 * - jamais runInBackground() (pas de processus détaché en mutualisé) ni onOneServer() (un seul serveur) ;
 * - withoutOverlapping() partout : un cron qui déborde sur la minute suivante ne doit pas se doubler ;
 * - toutes les heures sont exprimées dans config('app.timezone').
 *
 * `php artisan queue:work --stop-when-empty --max-time=50` est la seconde ligne cron (P17-03), pas une
 * entrée du planificateur. Les commandes elles-mêmes sont dans app/Console/Commands ; leur logique dans
 * app/Application/Retention et app/Application/Maintenance.
 */

$timezone = (string) config('app.timezone', 'UTC');

/*
 * Chaque minute.
 */
Schedule::command('mail:flush-outbox')
    ->everyMinute()
    ->timezone($timezone)
    ->withoutOverlapping(10)
    ->description('Envoie les e-mails en attente dans mail_outbox (50 au plus) dès qu’un SMTP est disponible');

/*
 * Quotidien à 03:00 : purges et anonymisations (DATABASE_ENGINEERING.md §6), dans l'ordre des dépendances
 * (les comptes anonymisés libèrent leurs sessions et jetons avant la purge des sessions).
 */
$daily = [
    'otp:purge' => 'Supprime les codes OTP expirés ou consommés depuis plus de 24 h',
    'mail:purge-logs' => 'Supprime les journaux d’envoi (mail_logs) de plus de 180 jours',
    'audit:purge' => 'Supprime le journal d’audit au-delà de la rétention configurée (90 jours par défaut)',
    'accounts:anonymize' => 'Anonymise les comptes supprimés depuis plus de 30 jours',
    'cases:anonymize' => 'Anonymise les dossiers clos ou archivés depuis plus de 5 ans',
    'sessions:purge' => 'Supprime les sessions en base inactives (durée de session + 1 jour)',
    'sync:prune' => 'Supprime les reçus de synchronisation de plus de 90 jours et révoque les appareils inactifs depuis 180 jours',
    'idempotency:purge' => 'Supprime les réponses idempotentes de l’API dont la fenêtre de rejeu est passée (P5-09, ADR-32)',
    'content-events:purge' => 'Supprime les compteurs d’événements de contenu de plus de 30 jours (P10-06)',
];

foreach ($daily as $command => $description) {
    Schedule::command($command)
        ->dailyAt('03:00')
        ->timezone($timezone)
        ->withoutOverlapping(180)
        ->description($description);
}

/*
 * Quotidien à 03:30 : purges de fichiers (P1-16), après les purges de données.
 */
$dailyFiles = [
    'reports:purge' => 'Supprime les fichiers de generated_reports au-delà d’expires_at (ligne marquée si le journal la référence, supprimée sinon)',
    'cache:purge-expired' => 'Supprime les fichiers expirés du cache file (storage/framework/cache/data) ; sans effet pour les autres pilotes',
    'accounts:purge-exports' => 'Supprime les exports RGPD (me/export) dont le lien de téléchargement a expiré (P6-06)',
];

foreach ($dailyFiles as $command => $description) {
    Schedule::command($command)
        ->dailyAt('03:30')
        ->timezone($timezone)
        ->withoutOverlapping(180)
        ->description($description);
}

/*
 * Mensuel, le 1er à 04:00 : instantanés du mois écoulé des indicateurs intégrés (indicator_values, P1-16).
 */
Schedule::command('indicators:snapshot')
    ->monthlyOn(1, '04:00')
    ->timezone($timezone)
    ->withoutOverlapping(180)
    ->description('Calcule les indicateurs intégrés du mois précédent (national et par département) dans indicator_values');

/*
 * Rapports planifiés (P10-07) : chaque jour à 04:15 (après l'instantané mensuel de 04:00), les planifications actives
 * échues enfilent leurs jobs GenerateReport ; l'envoi par e-mail suit la fin de la génération.
 */
Schedule::command('reports:generate')
    ->dailyAt('04:15')
    ->timezone($timezone)
    ->withoutOverlapping(180)
    ->description('Enfile la génération des rapports planifiés échus (report_schedules) et envoie les fichiers par e-mail');

/*
 * Escalade RG-05 / Q27 (P5-07) : SOS sans accusé à 5 min ± 1 vers le point focal national, rappel puis escalade des
 * signalements et besoins (24 h / 48 h). Avant heartbeat:ping ; pose le marqueur `alerts:escalate:last_success_at`.
 */
Schedule::command('alerts:escalate')
    ->everyMinute()
    ->timezone($timezone)
    ->withoutOverlapping(10)
    ->description('Escalade les SOS sans accusé (5 min) au point focal national, rappelle puis escalade les signalements et besoins sans accusé (24 h / 48 h)');

/*
 * Rejeu des notifications d'alerte (P5-03, D7) : push, SMS et e-mails en attente ou échoués, SOS d'abord, SMS de repli,
 * abonnements push disparus supprimés. Après alerts:escalate (dont il rejoue les envois) et avant heartbeat:ping ;
 * pose le marqueur `notifications:retry:last_success_at`.
 */
Schedule::command('notifications:retry')
    ->everyMinute()
    ->timezone($timezone)
    ->withoutOverlapping(10)
    ->description('Rejoue les notifications d’alerte en attente ou échouées (push, SMS, e-mail) et supprime les abonnements push disparus');

/*
 * Sondages du fil (P7-07) : clôture des sondages échus et cloche « Sondage terminé » à leur auteur·rice. Le vote est
 * refusé dès l'échéance même avant ce passage (Poll::isOpen) : cinq minutes suffisent pour la notification.
 */
Schedule::command('polls:close')
    ->everyFiveMinutes()
    ->timezone($timezone)
    ->withoutOverlapping(10)
    ->description('Clôt les sondages arrivés à échéance et envoie « Sondage terminé » à leur auteur·rice');

/*
 * Événements (P8-04) : rappel de la veille aux participant·es confirmé·es (cloche, push, e-mail `rappel-evenement`),
 * une seule fois par événement (`events.reminder_sent_at`). Un quart d'heure de précision suffit pour « demain ».
 */
Schedule::command('events:remind')
    ->everyFifteenMinutes()
    ->timezone($timezone)
    ->withoutOverlapping(10)
    ->description('Envoie le rappel de la veille aux participant·es des événements qui commencent dans les 24 h');

/*
 * Signal de vie vers la supervision externe (D7, NFR-03) : enregistré en dernier pour être émis après les
 * autres tâches de la minute. À conditionner au succès d'`alerts:escalate` (marqueur `alerts:escalate:last_success_at`,
 * livré) et de `notifications:retry` (marqueur `notifications:retry:last_success_at`, livré) — P3-07 : un cron qui tourne mais échoue doit déclencher l'alerte.
 */
Schedule::command('heartbeat:ping')
    ->everyMinute()
    ->timezone($timezone)
    ->withoutOverlapping(5)
    ->description('Signal de vie vers l’URL de supervision (HEARTBEAT_URL)');

/*
 * Entrées livrées par les phases suivantes : à décommenter avec la commande correspondante. Une commande
 * planifiée mais absente fait échouer `schedule:run`, et le planificateur ne peut pas interroger Artisan
 * pendant son propre chargement — d'où ces lignes en commentaire plutôt qu'une condition.
 *
 * P14-03 — newsletter (lots de 200) :
 *   Schedule::command('newsletter:process')->everyMinute()->timezone($timezone)->withoutOverlapping(10);
 * P14-04 — passerelles réseaux sociaux (D4-bis) :
 *   Schedule::command('gateways:publish')->everyMinute()->timezone($timezone)->withoutOverlapping(10);
 * P13-03 — sauvegardes (mysqldump + fichiers vers stockage externe chiffré) :
 *   Schedule::command('backups:run')->dailyAt('02:00')->timezone($timezone)->withoutOverlapping(180);
 */
