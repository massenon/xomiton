<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Application\Alerts\Dispatch\Recipients\RecipientTarget;
use App\Application\Mail\EmailTemplateService;
use App\Application\Shared\NotificationPreferences;
use App\Domain\Alerts\Dispatch\RecipientKind;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\AuditLog;
use App\Models\PushSubscription;
use App\Notifications\XomitonNotification;
use App\Support\Audit;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Envoi des notifications d'une alerte dans la requête (le « SosDispatcher » de P5-03, D7) :
 *  1. planifie une ligne `alert_notifications` par personne, objet et canal AVANT tout envoi (si PHP est interrompu,
 *     le cron `notifications:retry` rejoue ce qui reste) ; une ligne déjà envoyée ou abandonnée n'est jamais renvoyée ;
 *  2. écrit la cloche (canal `database`, immédiat) ;
 *  3. dépose l'e-mail dans la boîte d'envoi (requête) ou l'envoie (cron) : aucun SMTP dans une requête ;
 *  4. confie push et SMS au DeliveryRunner (pool HTTP, 3 s par fournisseur, budget global) ;
 *  5. SOS : SMS de repli au compte sans abonnement push, ou dont tous les push viennent d'échouer (plafond en personnes
 *     pour les admins de garde, sur tous les objets SOS : AdminSmsQuota) ; le contact de confiance et les fiches sans
 *     compte reçoivent le SMS directement ; « fausse alerte » et « en sécurité » partent aussi par SMS au compte joint
 *     par SMS pour ce SOS ou sans push (hors admins : leur plafond a pu les en priver) ;
 *  6. journal sans donnée personnelle et audit résumé sans IP.
 * Les textes, la discrétion et les charges utiles sont ceux des notifications de la Phase 3 (NotificationBuilder).
 */
final class AlertDispatcher
{
    /** Objets dont le SMS part directement aux contacts sans compte (contact de confiance, fiches d'annuaire). */
    private const SMS_TO_CONTACTS = [
        NotificationPurpose::SosTriggered,
        NotificationPurpose::SosUnacknowledged,
        NotificationPurpose::SosCancelled,
        NotificationPurpose::SosSafe,
    ];

    /** Suites d'un SOS : SMS au compte qui a reçu le SOS par SMS (il peut n'avoir ni push ni cloche lue). */
    private const SMS_CLOSING = [
        NotificationPurpose::SosCancelled,
        NotificationPurpose::SosSafe,
    ];

    /** Objets qui portent un e-mail (`alerte-routee`) à la personne qui doit agir. */
    private const MAIL_PURPOSES = [
        NotificationPurpose::AlertRouted,
        NotificationPurpose::AlertReminder,
        NotificationPurpose::AlertEscalated,
        NotificationPurpose::SosUnacknowledged,
    ];

    public function __construct(
        private readonly DeliveryRunner $runner,
        private readonly NotificationBuilder $builder,
    ) {}

    /**
     * @param  iterable<RecipientTarget>  $targets
     * @param  array<string, mixed>  $extra  données de la notification (NotificationBuilder::for)
     */
    public function dispatch(Alert $alert, NotificationPurpose $purpose, iterable $targets, DispatchContext $context, array $extra = []): DispatchResult
    {
        $result = new DispatchResult($purpose);
        $recorder = DeliveryRecorder::fromConfig();
        $quota = $purpose->allowsSmsFallback() ? AdminSmsQuota::forAlert($alert) : AdminSmsQuota::none();
        $plans = [];

        foreach ($targets as $target) {
            $result->recipients++;
            $plan = new TargetPlan($target, $this->builder->for($purpose, $alert, $target, $extra));
            $this->plan($alert, $purpose, $plan, $context, $quota);
            $plans[] = $plan;
        }

        if ($plans === []) {
            Log::critical('alerts.no_recipient', ['ulid' => $alert->ulid, 'purpose' => $purpose->value]);
            Audit::logWithoutRequest('alerts.no_recipient', $alert, 'purpose='.$purpose->value, AuditLog::ACTOR_SYSTEM);

            return $result;
        }

        foreach ($plans as $plan) {
            $result->planned += count(array_filter($plan->rows(), static fn (AlertNotification $row): bool => ! $row->status->isFinal()));
        }

        foreach ($plans as $plan) {
            $this->deliverDatabase($plan, $purpose, $context, $recorder, $result);
            $this->deliverMail($alert, $plan, $purpose, $context, $recorder, $result);
        }

        $this->deliverExternal($plans, $purpose, $context, $recorder, $result);

        if ($purpose->allowsSmsFallback()) {
            $this->fallbackToSms($alert, $plans, $purpose, $context, $recorder, $result, $quota);
        }

        $elapsed = (int) round($context->budget->elapsed() * 1000);

        Log::info('alerts.dispatched', [
            'ulid' => $alert->ulid,
            'purpose' => $purpose->value,
            'planned' => $result->planned,
            'sent' => $result->sent,
            'failed' => $result->failed,
            'pending' => $result->pending,
            'skipped' => $result->skipped,
            'sms_fallback' => $result->smsFallbacks,
            'elapsed_ms' => $elapsed,
        ]);

        Audit::logWithoutRequest('alerts.notified', $alert, $result->detail($elapsed), AuditLog::ACTOR_SYSTEM);

        return $result;
    }

    /**
     * Canaux d'une personne (NOTIFICATIONS §1-§2, P5-03) et lignes correspondantes, toutes planifiées avant l'envoi.
     */
    private function plan(Alert $alert, NotificationPurpose $purpose, TargetPlan $plan, DispatchContext $context, AdminSmsQuota $quota): void
    {
        $target = $plan->target;
        $notification = $plan->notification;

        if ($target->user !== null) {
            $prefs = $target->user->loadMissing('settings')->settings?->notification_prefs;
            $allows = static fn (string $channel): bool => NotificationPreferences::allows($prefs, $notification->category(), $channel);

            if ($allows(NotificationPreferences::CHANNEL_IN_APP)) {
                $plan->database = $this->row($alert, $purpose, NotificationChannel::Database, $target->reference, $target, $context);
            }

            if ($allows(NotificationPreferences::CHANNEL_PUSH)) {
                foreach ($target->user->pushSubscriptions()->orderBy('id')->get() as $subscription) {
                    $plan->push[] = [$this->row($alert, $purpose, NotificationChannel::Push, AlertNotification::SUBSCRIPTION_RECIPIENT_PREFIX.$subscription->getKey(), $target, $context, $subscription), $subscription];
                }
            }

            if ($this->wantsMail($purpose, $target, $notification) && $allows(NotificationPreferences::CHANNEL_EMAIL) && $target->email !== null) {
                $plan->mail = $this->row($alert, $purpose, NotificationChannel::Mail, $target->reference, $target, $context);
            }

            // Escalade : SMS toujours à la cible (SosUnacknowledged::wantsSms) ; SOS : SMS d'emblée sans abonnement push.
            $direct = $purpose === NotificationPurpose::SosUnacknowledged && $target->primary;
            $fallback = $purpose->allowsSmsFallback() && $plan->push === [] && (! $target->isTeam() || $quota->allows($target->reference));
            $closing = in_array($purpose, self::SMS_CLOSING, true)
                && ($this->reachedBySms($alert, $target) || ($plan->push === [] && ! $target->isTeam()));

            if ($direct || $fallback || $closing) {
                if ($target->phone === null) {
                    Log::notice('alerts.no_sms_route', ['ulid' => $alert->ulid, 'user' => $target->user->getKey()]);
                } else {
                    $plan->sms = $this->row($alert, $purpose, NotificationChannel::Sms, $target->reference, $target, $context);

                    if ($target->isTeam() && ($direct || $fallback)) {
                        $quota->take($target->reference);
                    }
                }
            }

            return;
        }

        if (in_array($purpose, self::SMS_TO_CONTACTS, true)) {
            $plan->sms = $this->row($alert, $purpose, NotificationChannel::Sms, $target->reference, $target, $context);

            if ($target->phone === null && ! $plan->sms->status->isFinal()) {
                $plan->sms->markSkipped(AlertNotification::SKIP_NO_PHONE);
                Log::warning('alerts.contact_unreachable', ['ulid' => $alert->ulid, 'contact' => $target->contact?->getKey(), 'channel' => 'sms']);
            }
        }

        if ($target->kind !== RecipientKind::Trusted && $this->wantsMail($purpose, $target, $notification)) {
            $plan->mail = $this->row($alert, $purpose, NotificationChannel::Mail, $target->reference, $target, $context);

            if ($target->email === null && ! $plan->mail->status->isFinal()) {
                $plan->mail->markSkipped(AlertNotification::SKIP_NO_ADDRESS);
                Log::warning('alerts.contact_unreachable', ['ulid' => $alert->ulid, 'contact' => $target->contact?->getKey(), 'channel' => 'mail']);
            }
        }
    }

    /** Le SOS (ou son escalade) a été remis par SMS à ce compte. */
    private function reachedBySms(Alert $alert, RecipientTarget $target): bool
    {
        return AlertNotification::query()
            ->forAlert($alert)
            ->ofPurpose(...AdminSmsQuota::PURPOSES)
            ->onChannel(NotificationChannel::Sms)
            ->where('recipient', $target->reference)
            ->where('status', DeliveryStatus::Sent)
            ->exists();
    }

    private function wantsMail(NotificationPurpose $purpose, RecipientTarget $target, XomitonNotification $notification): bool
    {
        return $target->primary && in_array($purpose, self::MAIL_PURPOSES, true) && $notification->mailTemplate() !== null;
    }

    /**
     * Ligne de la personne pour ce canal : créée en attente, ou reprise telle quelle (index unique : un événement reçu
     * deux fois ne crée ni ne renvoie rien). Bail d'une minute : le cron ne la reprend pas pendant l'envoi en cours.
     */
    private function row(Alert $alert, NotificationPurpose $purpose, NotificationChannel $channel, string $recipient, RecipientTarget $target, DispatchContext $context, ?PushSubscription $subscription = null): AlertNotification
    {
        return AlertNotification::query()->firstOrCreate(
            [
                'alert_id' => $alert->getKey(),
                'purpose' => $purpose,
                'channel' => $channel,
                'recipient' => $recipient,
            ],
            [
                'directory_contact_id' => $target->contact?->getKey(),
                'user_id' => $target->user?->getKey(),
                'push_subscription_id' => $subscription?->getKey(),
                'status' => DeliveryStatus::Pending,
                'attempts' => 0,
                'next_retry_at' => $context->now->addMinute(),
            ],
        );
    }

    private function deliverDatabase(TargetPlan $plan, NotificationPurpose $purpose, DispatchContext $context, DeliveryRecorder $recorder, DispatchResult $result): void
    {
        $row = $plan->database;

        if ($row === null || $row->status->isFinal() || $plan->target->user === null) {
            return;
        }

        try {
            Notification::sendNow($plan->target->user, $plan->notification, [XomitonNotification::CHANNEL_DATABASE]);
            $row->markSent(NotificationChannel::Database->value, $context->now);
            $result->count(DeliveryRecorder::SENT);
        } catch (Throwable $e) {
            report($e);
            $result->count($recorder->failed($row, DeliveryError::fromThrowable($e), NotificationChannel::Database->value, $purpose, $context->now));
        }
    }

    private function deliverMail(Alert $alert, TargetPlan $plan, NotificationPurpose $purpose, DispatchContext $context, DeliveryRecorder $recorder, DispatchResult $result): void
    {
        $row = $plan->mail;
        $target = $plan->target;

        if ($row === null || $row->status->isFinal() || $target->email === null) {
            return;
        }

        $result->count($this->sendMail($alert, $row, $plan->notification, $target, $purpose, $context->deferMail, $recorder, $context));
    }

    /**
     * E-mail `alerte-routee` : dépôt dans la boîte d'envoi (requête) ou envoi direct (cron). Une fiche sans compte
     * reçoit son nom et, à la place du lien de connexion, le lien signé d'accusé (elle n'a pas d'espace).
     */
    public function sendMail(Alert $alert, AlertNotification $row, XomitonNotification $notification, RecipientTarget $target, NotificationPurpose $purpose, bool $defer, DeliveryRecorder $recorder, DispatchContext $context): string
    {
        try {
            $data = $notification->toMail($target->notifiable());
            $vars = $data->variables;

            if ($target->user === null && $target->contact !== null) {
                $vars += [
                    // Fiches « NOM Prénom » comme « Prénom NOM » : le nom complet, plutôt qu'un prénom deviné.
                    'user_first_name' => trim((string) $target->contact->name),
                    'login_link' => AlertLinks::for($alert, $target),
                ];
            }

            $log = $defer
                ? EmailTemplateService::enqueue($data->slug, (string) $target->email, $vars, $data->context)
                : EmailTemplateService::send($data->slug, (string) $target->email, $vars, $data->context);
        } catch (Throwable $e) {
            report($e);

            return $recorder->failed($row, DeliveryError::fromThrowable($e), NotificationChannel::Mail->value, $purpose, $context->now);
        }

        if (! $log->ok) {
            return $recorder->failed($row, DeliveryError::mask((string) ($log->error ?? AlertNotification::ERROR_MAIL_NOT_SENT)), NotificationChannel::Mail->value, $purpose, $context->now);
        }

        $row->markSent($defer ? 'outbox' : $log->transport->value, $context->now);

        return DeliveryRecorder::SENT;
    }

    /**
     * @param  list<TargetPlan>  $plans
     */
    private function deliverExternal(array $plans, NotificationPurpose $purpose, DispatchContext $context, DeliveryRecorder $recorder, DispatchResult $result): void
    {
        $deliveries = [];

        foreach ($plans as $plan) {
            $target = $plan->target;

            if ($target->user !== null && $plan->push !== []) {
                $message = $plan->notification->toPush($target->user);

                foreach ($plan->push as [$row, $subscription]) {
                    if (! $row->status->isFinal()) {
                        $deliveries[] = PlannedDelivery::push($row, $subscription, $message);
                    }
                }
            }

            if ($plan->sms !== null && ($delivery = $this->smsDelivery($plan->sms, $plan)) !== null) {
                $deliveries[] = $delivery;
            }
        }

        $this->run($deliveries, $purpose, $context, $recorder, $result);
    }

    /**
     * Repli SMS immédiat : comptes dont tous les push viennent d'échouer et qui n'ont pas encore de SMS.
     *
     * @param  list<TargetPlan>  $plans
     */
    private function fallbackToSms(Alert $alert, array $plans, NotificationPurpose $purpose, DispatchContext $context, DeliveryRecorder $recorder, DispatchResult $result, AdminSmsQuota $quota): void
    {
        $deliveries = [];

        foreach ($plans as $plan) {
            $target = $plan->target;

            if ($target->user === null || $plan->sms !== null || ! $plan->allPushFailed() || $target->phone === null) {
                continue;
            }

            if ($target->isTeam() && ! $quota->allows($target->reference)) {
                continue;
            }

            $plan->sms = $this->row($alert, $purpose, NotificationChannel::Sms, $target->reference, $target, $context);

            if ($target->isTeam()) {
                $quota->take($target->reference);
            }

            $result->smsFallbacks++;
            $result->planned++;

            if (($delivery = $this->smsDelivery($plan->sms, $plan)) !== null) {
                $deliveries[] = $delivery;
            }
        }

        $this->run($deliveries, $purpose, $context, $recorder, $result);
    }

    private function smsDelivery(AlertNotification $row, TargetPlan $plan): ?PlannedDelivery
    {
        if ($row->status->isFinal()) {
            return null;
        }

        $text = $plan->notification->toSms($plan->target->notifiable());

        if ($text === null || $plan->target->phone === null) {
            $row->markSkipped(AlertNotification::SKIP_NO_PHONE);

            return null;
        }

        return PlannedDelivery::sms($row, $plan->target->phone, $text);
    }

    /**
     * @param  list<PlannedDelivery>  $deliveries
     */
    private function run(array $deliveries, NotificationPurpose $purpose, DispatchContext $context, DeliveryRecorder $recorder, DispatchResult $result): void
    {
        if ($deliveries === []) {
            return;
        }

        foreach ($this->runner->run($deliveries, $context->budget) as $outcome) {
            $result->count($recorder->record($outcome, $purpose, $context->now));
        }
    }
}
