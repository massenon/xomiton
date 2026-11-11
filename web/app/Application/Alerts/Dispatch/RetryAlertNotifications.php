<?php

declare(strict_types=1);

namespace App\Application\Alerts\Dispatch;

use App\Application\Alerts\Dispatch\Recipients\RecipientResolver;
use App\Application\Alerts\Dispatch\Recipients\RecipientTarget;
use App\Domain\Alerts\Dispatch\DeliveryBudget;
use App\Domain\Alerts\Dispatch\RetryPolicy;
use App\Enums\AlertStatus;
use App\Enums\DeliveryStatus;
use App\Enums\NotificationChannel;
use App\Enums\NotificationPurpose;
use App\Models\Alert;
use App\Models\AlertNotification;
use App\Models\AuditLog;
use App\Models\User;
use App\Notifications\XomitonNotification;
use App\Support\Audit;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

/**
 * Logique de `notifications:retry` (P5-03, ARCH §6, D7), chaque minute : rejoue les lignes `alert_notifications` en
 * attente ou en échec (objets rejouables, échéance atteinte, sous le plafond), SOS d'abord ; abandonne celles dont
 * l'alerte est prise en charge, annulée ou close (D8) ou dont la personne n'est plus joignable ; ajoute le SMS de repli
 * aux comptes dont tous les push d'un SOS ont échoué ; supprime les abonnements disparus (404 / 410). Chaque ligne passe
 * `sent` avant la suivante (idempotent) ; une erreur sur une ligne n'empêche pas les autres.
 */
final class RetryAlertNotifications
{
    public function __construct(
        private readonly DeliveryRunner $runner,
        private readonly NotificationBuilder $builder,
        private readonly RecipientResolver $resolver,
        private readonly AlertDispatcher $dispatcher,
    ) {}

    public function run(CarbonImmutable $now, ?int $limit = null, bool $dryRun = false): RetryResult
    {
        $limit = max(1, $limit ?? (int) config('xomiton.alerts.retry_batch_size', 100));
        $result = new RetryResult($dryRun);
        $budget = RetryPolicyFactory::consoleBudget();
        $recorder = DeliveryRecorder::fromConfig();
        $deliveries = [];

        $rows = AlertNotification::query()
            ->retryable($now)
            ->sosFirst()
            ->with(['alert.zone', 'alert.commune', 'alert.user.profile', 'directoryContact', 'pushSubscription'])
            ->limit($limit)
            ->get();

        foreach ($rows as $row) {
            $result->examined++;

            try {
                $delivery = $this->prepare($row, $now, $result, $recorder, $dryRun);

                if ($delivery !== null) {
                    $deliveries[] = $delivery;
                }
            } catch (Throwable $e) {
                $this->error($result, $row, $e);
            }
        }

        if (! $dryRun) {
            $this->deliver($deliveries, $budget, $recorder, $now, $result);
            $this->fallbacks($now, $budget, $recorder, $result);
        }

        return $result;
    }

    /**
     * « Rejouer maintenant » depuis l'administration (P10) : même traitement pour une ligne, hors échéance et hors
     * plafond, journalisé avec l'acteur.
     */
    public function retryOne(AlertNotification $row, ?User $actor = null): AlertNotification
    {
        $row->loadMissing(['alert.zone', 'alert.commune', 'alert.user.profile', 'directoryContact', 'pushSubscription']);

        if ($actor !== null) {
            Gate::forUser($actor)->authorize('reassign', $row->alert);
        }

        $now = CarbonImmutable::now();
        $result = new RetryResult(false);
        $recorder = DeliveryRecorder::fromConfig();

        if (! $row->status->isFinal()) {
            $delivery = $this->prepare($row, $now, $result, $recorder, false);
            $this->deliver($delivery === null ? [] : [$delivery], RetryPolicyFactory::consoleBudget(), $recorder, $now, $result);
        }

        $row->refresh();

        if ($actor !== null) {
            Audit::log('alerts.notification_retried', $row, sprintf('canal=%s ; resultat=%s', $row->channel->value, $row->status->value), $actor);
        }

        return $row;
    }

    /**
     * Ligne abandonnée si l'objectif est atteint (quelqu'un a pris l'alerte) ou n'a plus de sens (annulée, close) :
     * plus aucun SMS payant ni push tardif (D8). « En sécurité » (statut `traite` + `safe_at`) ne suffit pas :
     * l'équipe doit quand même rappeler la personne (D18).
     */
    public static function irrelevance(Alert $alert): ?string
    {
        if ($alert->isCancelled()) {
            return AlertNotification::SKIP_CANCELLED;
        }

        if ($alert->isAcknowledged() || in_array($alert->status, [AlertStatus::Acknowledged, AlertStatus::InProgress, AlertStatus::Closed], true)) {
            return AlertNotification::SKIP_HANDLED;
        }

        if ($alert->status->isTerminal() && ! $alert->isSafe()) {
            return AlertNotification::SKIP_HANDLED;
        }

        return null;
    }

    private function prepare(AlertNotification $row, CarbonImmutable $now, RetryResult $result, DeliveryRecorder $recorder, bool $dryRun): ?PlannedDelivery
    {
        $alert = $row->alert;
        $reason = $alert === null ? AlertNotification::SKIP_UNAVAILABLE : self::irrelevance($alert);
        $target = null;

        if ($reason === null && $alert !== null) {
            $target = $this->resolver->resolve($row, $alert);
            $reason = is_string($target) ? $target : null;
        }

        if ($reason === null && $row->channel === NotificationChannel::Push && $row->pushSubscription === null) {
            $reason = AlertNotification::SKIP_GONE;
        }

        if ($reason === null && in_array($row->channel, [NotificationChannel::Push, NotificationChannel::Database], true) && $target instanceof RecipientTarget && $target->user === null) {
            $reason = AlertNotification::SKIP_UNAVAILABLE;
        }

        if ($reason !== null || ! $target instanceof RecipientTarget || $alert === null) {
            $result->skip($row, (string) ($reason ?? AlertNotification::SKIP_UNAVAILABLE));

            if (! $dryRun) {
                $row->markSkipped((string) ($reason ?? AlertNotification::SKIP_UNAVAILABLE));
            }

            return null;
        }

        if ($dryRun) {
            $result->retry($row);

            return null;
        }

        $notification = $this->builder->for($row->purpose, $alert, $target);

        return match ($row->channel) {
            NotificationChannel::Database => $this->database($row, $target, $notification, $now, $result, $recorder),
            NotificationChannel::Mail => $this->mail($alert, $row, $target, $notification, $now, $result, $recorder),
            NotificationChannel::Push => PlannedDelivery::push($row, $row->pushSubscription, $notification->toPush($target->user)),
            NotificationChannel::Sms => $this->sms($row, $target, $notification, $result),
        };
    }

    private function database(AlertNotification $row, RecipientTarget $target, XomitonNotification $notification, CarbonImmutable $now, RetryResult $result, DeliveryRecorder $recorder): null
    {
        try {
            Notification::sendNow($target->user, $notification, [XomitonNotification::CHANNEL_DATABASE]);
            $row->markSent(NotificationChannel::Database->value, $now);
            $result->count($row, DeliveryRecorder::SENT);
        } catch (Throwable $e) {
            report($e);
            $this->counted($row, $recorder->failed($row, DeliveryError::fromThrowable($e), NotificationChannel::Database->value, $row->purpose, $now), $result);
        }

        return null;
    }

    private function mail(Alert $alert, AlertNotification $row, RecipientTarget $target, XomitonNotification $notification, CarbonImmutable $now, RetryResult $result, DeliveryRecorder $recorder): null
    {
        if ($target->email === null) {
            $row->markSkipped(AlertNotification::SKIP_NO_ADDRESS);
            $result->skip($row, AlertNotification::SKIP_NO_ADDRESS);

            return null;
        }

        $outcome = $this->dispatcher->sendMail($alert, $row, $notification, $target, $row->purpose, false, $recorder, DispatchContext::console($now));
        $this->counted($row, $outcome, $result);

        return null;
    }

    private function sms(AlertNotification $row, RecipientTarget $target, XomitonNotification $notification, RetryResult $result): ?PlannedDelivery
    {
        $text = $notification->toSms($target->notifiable());

        if ($text === null || $target->phone === null) {
            $row->markSkipped(AlertNotification::SKIP_NO_PHONE);
            $result->skip($row, AlertNotification::SKIP_NO_PHONE);

            return null;
        }

        return PlannedDelivery::sms($row, $target->phone, $text);
    }

    /**
     * @param  list<PlannedDelivery>  $deliveries
     */
    private function deliver(array $deliveries, DeliveryBudget $budget, DeliveryRecorder $recorder, CarbonImmutable $now, RetryResult $result): void
    {
        if ($deliveries === []) {
            return;
        }

        foreach ($this->runner->run($deliveries, $budget) as $outcome) {
            $row = $outcome->delivery->row;

            try {
                $this->counted($row, $recorder->record($outcome, $row->purpose, $now), $result);
            } catch (Throwable $e) {
                $this->error($result, $row, $e);
            }
        }
    }

    /**
     * SMS de repli au rejeu : compte dont tous les push d'un SOS ont échoué au moins `sms_fallback_after_push_failures`
     * fois (ou visaient un abonnement disparu) et qui n'a pas encore de SMS pour cet objet ; plafond par SOS pour les
     * admins de garde, en personnes et sur tous les objets SOS (AdminSmsQuota, `sos_admin_sms_max`).
     */
    private function fallbacks(CarbonImmutable $now, DeliveryBudget $budget, DeliveryRecorder $recorder, RetryResult $result): void
    {
        $policy = RetryPolicyFactory::fromConfig();
        $threshold = (int) config('xomiton.alerts.sms_fallback_after_push_failures', 2);
        $deliveries = [];

        $groups = AlertNotification::query()
            ->onChannel(NotificationChannel::Push)
            ->ofPurpose(NotificationPurpose::SosTriggered, NotificationPurpose::SosUnacknowledged)
            ->whereIn('status', [DeliveryStatus::Failed, DeliveryStatus::Skipped])
            ->whereNotNull('user_id')
            ->where('created_at', '>=', $now->subDay())
            ->whereHas('alert', static fn (Builder $alert) => $alert->whereNull('cancelled_at')->whereNull('acknowledged_at')
                ->whereNotIn('status', [AlertStatus::Acknowledged, AlertStatus::InProgress, AlertStatus::Closed]))
            ->get(['alert_id', 'purpose', 'user_id', 'directory_contact_id'])
            ->unique(static fn (AlertNotification $row): string => $row->alert_id.'|'.$row->purpose->value.'|'.$row->user_id);

        foreach ($groups as $sample) {
            try {
                $delivery = $this->fallback($sample, $policy, $threshold, $result);

                if ($delivery !== null) {
                    $deliveries[] = $delivery;
                }
            } catch (Throwable $e) {
                $this->error($result, $sample, $e);
            }
        }

        $this->deliver($deliveries, $budget, $recorder, $now, $result);
    }

    private function fallback(AlertNotification $sample, RetryPolicy $policy, int $threshold, RetryResult $result): ?PlannedDelivery
    {
        $userRef = AlertNotification::USER_RECIPIENT_PREFIX.$sample->user_id;
        $siblings = AlertNotification::query()->forAlert($sample->alert_id)->ofPurpose($sample->purpose);

        $pushRows = (clone $siblings)->onChannel(NotificationChannel::Push)->where('user_id', $sample->user_id)->get();
        $exhausted = $pushRows->every(static fn (AlertNotification $row): bool => $row->status === DeliveryStatus::Skipped
            || ($row->status === DeliveryStatus::Failed && $policy->shouldFallbackToSms($row->attempts, $threshold)));

        if (! $exhausted || (clone $siblings)->onChannel(NotificationChannel::Sms)->where('recipient', $userRef)->exists()) {
            return null;
        }

        $alert = Alert::query()->with(['zone', 'commune', 'user'])->find($sample->alert_id);

        if ($alert === null) {
            return null;
        }

        if ($sample->directory_contact_id === null && ! AdminSmsQuota::forAlert($alert)->allows($userRef)) {
            return null;
        }

        $row = AlertNotification::query()->firstOrCreate(
            ['alert_id' => $alert->getKey(), 'purpose' => $sample->purpose, 'channel' => NotificationChannel::Sms, 'recipient' => $userRef],
            ['user_id' => $sample->user_id, 'directory_contact_id' => $sample->directory_contact_id, 'status' => DeliveryStatus::Pending, 'attempts' => 0],
        );

        $target = $this->resolver->resolve($row, $alert);

        if (! $target instanceof RecipientTarget) {
            $row->markSkipped($target);

            return null;
        }

        $result->smsFallbacks++;

        return $this->sms($row, $target, $this->builder->for($sample->purpose, $alert, $target), $result);
    }

    private function counted(AlertNotification $row, string $outcome, RetryResult $result): void
    {
        $result->count($row, $outcome);

        if ($outcome === DeliveryRecorder::EXHAUSTED) {
            Audit::logWithoutRequest('alerts.notification_exhausted', $row->alert, sprintf(
                'canal=%s ; destinataire=%s ; tentatives=%d',
                $row->channel->value,
                self::reference($row),
                $row->attempts,
            ), AuditLog::ACTOR_SYSTEM);
        }
    }

    private function error(RetryResult $result, AlertNotification $row, Throwable $e): void
    {
        $result->errors[] = ['id' => (int) $row->getKey(), 'error' => DeliveryError::fromThrowable($e)];

        Log::error('notifications:retry', ['id' => $row->getKey(), 'error' => DeliveryError::fromThrowable($e)]);
    }

    /**
     * Référence lisible sans donnée personnelle (`fiche#4`, `compte#12`, `confiance#7`) ; jamais `contact:` (clé masquée
     * par Audit::redactString).
     */
    private static function reference(AlertNotification $row): string
    {
        [$prefix, $id] = array_pad(explode(':', $row->personKey(), 2), 2, '');

        return match ($prefix.':') {
            AlertNotification::CONTACT_RECIPIENT_PREFIX => 'fiche#'.$id,
            AlertNotification::TRUSTED_RECIPIENT_PREFIX => 'confiance#'.$id,
            default => 'compte#'.$id,
        };
    }
}
