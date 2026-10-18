<?php

declare(strict_types=1);

use Illuminate\Console\Scheduling\Event;
use Illuminate\Console\Scheduling\Schedule;

/*
 * Planificateur de routes/console.php (P3-07, SOFTWARE_ARCHITECTURE.md §6) : liste attendue, fréquences,
 * withoutOverlapping partout, jamais runInBackground (cPanel mutualisé, D7).
 */

/**
 * @return array<string, Event> événements indexés par nom de commande Artisan
 */
function scheduledEvents(): array
{
    $events = [];

    foreach (app(Schedule::class)->events() as $event) {
        $events[commandName($event)] = $event;
    }

    return $events;
}

function commandName(Event $event): string
{
    preg_match('/artisan\'?\s+(\S+)/', (string) $event->command, $matches);

    return $matches[1] ?? (string) $event->command;
}

it('liste les tâches planifiées sans erreur', function (): void {
    $this->artisan('schedule:list')
        ->expectsOutputToContain('otp:purge')
        ->expectsOutputToContain('heartbeat:ping')
        ->assertSuccessful();
});

it('planifie les commandes attendues aux bonnes fréquences', function (): void {
    $events = scheduledEvents();

    $expected = [
        'mail:flush-outbox' => '* * * * *',
        'alerts:escalate' => '* * * * *',
        'notifications:retry' => '* * * * *',
        'heartbeat:ping' => '* * * * *',
        'polls:close' => '*/5 * * * *',
        'otp:purge' => '0 3 * * *',
        'mail:purge-logs' => '0 3 * * *',
        'audit:purge' => '0 3 * * *',
        'accounts:anonymize' => '0 3 * * *',
        'cases:anonymize' => '0 3 * * *',
        'sessions:purge' => '0 3 * * *',
        'sync:prune' => '0 3 * * *',
        'idempotency:purge' => '0 3 * * *',
        'reports:purge' => '30 3 * * *',
        'cache:purge-expired' => '30 3 * * *',
        'accounts:purge-exports' => '30 3 * * *',
        'indicators:snapshot' => '0 4 1 * *',
    ];

    expect(array_keys($events))->toEqualCanonicalizing(array_keys($expected));

    foreach ($expected as $command => $expression) {
        expect($events[$command]->expression)->toBe($expression, "fréquence de {$command}");
    }
});

it('protège chaque tâche contre le chevauchement, sans arrière-plan, dans le fuseau de l’application', function (): void {
    $events = scheduledEvents();

    expect($events)->not->toBeEmpty();

    foreach ($events as $command => $event) {
        expect($event->withoutOverlapping)->toBeTrue("{$command} doit être withoutOverlapping")
            ->and($event->runInBackground)->toBeFalse("{$command} ne doit pas tourner en arrière-plan")
            ->and($event->onOneServer)->toBeFalse("{$command} : onOneServer inutile sur un seul serveur")
            ->and($event->timezone)->toBe(config('app.timezone'), "{$command} : fuseau");
    }

    // Le signal de vie est émis en dernier dans la minute.
    expect(array_key_last($events))->toBe('heartbeat:ping');
});

it('planifie alerts:escalate chaque minute, avant le signal de vie (P5-07)', function (): void {
    $order = array_keys(scheduledEvents());

    expect($order)->toContain('alerts:escalate')
        ->and(array_search('alerts:escalate', $order, true))->toBeLessThan(array_search('heartbeat:ping', $order, true));
});

it('rend chaque commande planifiée exécutable', function (): void {
    $commands = array_keys(app(Illuminate\Contracts\Console\Kernel::class)->all());

    foreach (array_keys(scheduledEvents()) as $command) {
        expect($commands)->toContain($command);
    }
});

it('planifie notifications:retry chaque minute, après alerts:escalate et avant le signal de vie (P5-03)', function (): void {
    $order = array_keys(scheduledEvents());

    expect($order)->toContain('notifications:retry')
        ->and(array_search('notifications:retry', $order, true))->toBeGreaterThan(array_search('alerts:escalate', $order, true))
        ->and(array_search('notifications:retry', $order, true))->toBeLessThan(array_search('heartbeat:ping', $order, true));
});
