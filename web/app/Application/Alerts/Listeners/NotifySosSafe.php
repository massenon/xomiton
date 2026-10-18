<?php

declare(strict_types=1);

namespace App\Application\Alerts\Listeners;

use App\Application\Alerts\Dispatch\AlertDispatcher;
use App\Application\Alerts\Dispatch\DispatchContext;
use App\Application\Alerts\Dispatch\Recipients\RecipientResolver;
use App\Application\Alerts\Listeners\Concerns\DispatchesAlertNotifications;
use App\Domain\Alerts\Events\SosMarkedSafe;
use App\Enums\NotificationPurpose;

/**
 * SosMarkedSafe (« Je suis en sécurité », P5-02) → « Personne en sécurité » aux personnes déjà prévenues (P5-03) ;
 * les notifications SOS encore en attente continuent : l'équipe doit quand même rappeler la personne (D18).
 */
final class NotifySosSafe
{
    use DispatchesAlertNotifications;

    public function __construct(
        private readonly AlertDispatcher $dispatcher,
        private readonly RecipientResolver $resolver,
    ) {}

    public function handle(SosMarkedSafe $event): void
    {
        $alert = $this->loadAlert($event->alertId);

        if ($alert === null) {
            return;
        }

        $this->guarded($alert, function () use ($alert): void {
            $targets = $this->resolver->previouslyNotified($alert);

            if ($targets !== []) {
                $this->dispatcher->dispatch($alert, NotificationPurpose::SosSafe, $targets, DispatchContext::request());
            }
        });
    }
}
