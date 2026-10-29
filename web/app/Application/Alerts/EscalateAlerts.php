<?php

declare(strict_types=1);

namespace App\Application\Alerts;

use App\Application\Alerts\Results\EscalationRunResult;
use App\Application\Alerts\Support\AlertTransitions;
use App\Domain\Alerts\Enums\EscalationAction;
use App\Domain\Alerts\EscalationPolicy;
use App\Domain\Alerts\Events\AlertEscalated;
use App\Domain\Alerts\Events\AlertReminderDue;
use App\Domain\Alerts\ValueObjects\EscalationSubject;
use App\Enums\AlertType;
use App\Models\Alert;
use App\Models\AlertEscalation;
use App\Models\AuditLog;
use App\Models\DirectoryContact;
use App\Models\User;
use App\Support\Audit;
use App\Support\EscalationPolicyFactory;
use Carbon\CarbonImmutable;
use DateTimeImmutable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Logique d'`alerts:escalate` (RG-05, FR-ALT-05, Q27, P5-07), exécutée chaque minute par le cron cPanel (D7) :
 *  - SOS sans accusé à 5 min → escalade au point focal national (garde, sinon fiche nationale) ;
 *  - signalement / besoin sans accusé → rappel au contact routé (24 h), puis escalade (48 h) ;
 *  - sans point focal national, ou s'il est déjà le contact routé : escalade aux admins de garde (`to_contact_id` nul).
 * Une requête de sélection par exécution (borne `limit`), puis chaque alerte dans sa propre transaction, relue sous
 * verrou (un accusé concurrent l'emporte) ; une erreur sur une alerte n'empêche pas les suivantes. Idempotent :
 * `escalated_at` et `reminded_at` ne sont posés qu'une fois. Aucun envoi ici : AlertEscalated et AlertReminderDue
 * sont branchés par P5-03 ; tant qu'ils n'ont pas d'écouteur, la commande échoue sans rien écrire (isWired()). Journal `alerts.escalated` / `alerts.reminded`, acteur « système », sans IP.
 */
final class EscalateAlerts
{
    public function __construct(private readonly OnCallRoster $roster) {}

    /**
     * Vrai si AlertEscalated et AlertReminderDue ont un écouteur (envoi push / SMS / e-mail, P5-03). Sans écouteur,
     * une escalade poserait `escalated_at` sans prévenir personne et ne serait jamais rejouée : la commande refuse
     * alors de tourner (échec visible par la supervision) au lieu de consommer les échéances.
     */
    public function isWired(): bool
    {
        return Event::hasListeners(AlertEscalated::class) && Event::hasListeners(AlertReminderDue::class);
    }

    public function run(CarbonImmutable $now, ?int $limit = null, bool $dryRun = false): EscalationRunResult
    {
        $policy = EscalationPolicyFactory::fromConfig();
        $limit ??= (int) config('xomiton.alerts.escalation_batch', 200);
        $result = new EscalationRunResult($dryRun);

        foreach ($this->candidates($policy, $now, $limit) as $alert) {
            $result->examined++;

            try {
                $this->handle($alert, $policy, $now, $result);
            } catch (Throwable $exception) {
                $result->errors[] = ['ulid' => (string) $alert->ulid, 'error' => $exception->getMessage()];

                Log::error('alerts:escalate', ['ulid' => $alert->ulid, 'error' => $exception->getMessage()]);
            }
        }

        return $result;
    }

    /**
     * @return iterable<Alert>
     */
    private function candidates(EscalationPolicy $policy, CarbonImmutable $now, int $limit): iterable
    {
        return Alert::query()
            ->where(fn (Builder $query) => $query
                ->where(fn (Builder $due) => $due->awaitingEscalation($now, $policy))
                ->orWhere(fn (Builder $due) => $due->awaitingReminder($now, $policy)))
            // SOS d'abord : un SOS de 5 min ne doit jamais attendre derrière une file de rappels de signalements.
            ->orderByRaw('CASE WHEN type = ? THEN 0 ELSE 1 END', [AlertType::Sos->value])
            ->orderBy('created_at')
            ->orderBy('id')
            ->limit(max(1, $limit))
            ->get();
    }

    private function handle(Alert $alert, EscalationPolicy $policy, CarbonImmutable $now, EscalationRunResult $result): void
    {
        $action = $policy->decide(self::subject($alert), $now);

        if (! $action->requiresAction()) {
            $result->skipped++;

            return;
        }

        if ($result->dryRun) {
            $this->report($result, $alert, $action, $now, $action === EscalationAction::Escalate ? $this->target($alert, $now)[0] : null);

            return;
        }

        $action === EscalationAction::Escalate
            ? $this->escalate($alert, $policy, $now, $result)
            : $this->remind($alert, $policy, $now, $result);
    }

    private function escalate(Alert $alert, EscalationPolicy $policy, CarbonImmutable $now, EscalationRunResult $result): void
    {
        [$target, $admins] = $this->target($alert, $now);
        $reason = $alert->type === AlertType::Sos ? AlertEscalation::REASON_NO_ACK : AlertEscalation::REASON_NO_ACK_REPORT;

        $fresh = AlertTransitions::locked($alert, static function (Alert $fresh) use ($policy, $now, $target, $reason): ?Alert {
            if ($policy->decide(self::subject($fresh), $now) !== EscalationAction::Escalate) {
                return null;
            }

            $fresh->escalated_at = $now;
            $fresh->save();

            AlertEscalation::query()->create([
                'alert_id' => $fresh->getKey(),
                'from_contact_id' => $fresh->routed_to,
                'to_contact_id' => $target?->getKey(),
                'reason' => $reason,
            ]);

            return $fresh;
        });

        if ($fresh === null) {
            $result->skipped++;

            return;
        }

        $minutes = self::subject($fresh)->minutesSinceCreation($now);

        if ($target === null && $admins === []) {
            Log::warning('alerts:escalate : ni point focal national ni admin de garde.', ['ulid' => $fresh->ulid]);
        }

        Audit::logWithoutRequest('alerts.escalated', $fresh, sprintf(
            'motif=%s ; de=%s ; vers=%s ; apres=%d min',
            $reason,
            $fresh->routed_to === null ? '-' : '#'.$fresh->routed_to,
            ($target === null ? 'admins(' : '#'.$target->getKey().' + equipe(').count($admins).')',
            $minutes,
        ), AuditLog::ACTOR_SYSTEM);

        event(new AlertEscalated(
            alertId: (int) $fresh->getKey(),
            type: $fresh->type->value,
            fromContactId: $fresh->routed_to === null ? null : (int) $fresh->routed_to,
            toContactId: $target === null ? null : (int) $target->getKey(),
            adminIds: $admins,
            reason: $reason,
            minutesSinceCreation: $minutes,
            occurredAt: new DateTimeImmutable($now->toIso8601String()),
        ));

        $result->escalated++;
        $this->report($result, $fresh, EscalationAction::Escalate, $now, $target);
    }

    private function remind(Alert $alert, EscalationPolicy $policy, CarbonImmutable $now, EscalationRunResult $result): void
    {
        $fresh = AlertTransitions::locked($alert, static function (Alert $fresh) use ($policy, $now): ?Alert {
            if ($policy->decide(self::subject($fresh), $now) !== EscalationAction::Remind) {
                return null;
            }

            $fresh->reminded_at = $now;
            $fresh->save();

            return $fresh;
        });

        if ($fresh === null) {
            $result->skipped++;

            return;
        }

        $minutes = self::subject($fresh)->minutesSinceCreation($now);
        $admins = $this->adminIds($now);

        Audit::logWithoutRequest('alerts.reminded', $fresh, sprintf(
            'vers=%s ; apres=%d min',
            $fresh->routed_to === null ? '-' : '#'.$fresh->routed_to,
            $minutes,
        ), AuditLog::ACTOR_SYSTEM);

        event(new AlertReminderDue(
            alertId: (int) $fresh->getKey(),
            type: $fresh->type->value,
            routedContactId: $fresh->routed_to === null ? null : (int) $fresh->routed_to,
            adminIds: $admins,
            minutesSinceCreation: $minutes,
            occurredAt: new DateTimeImmutable($now->toIso8601String()),
        ));

        $result->reminded++;
        $this->report($result, $fresh, EscalationAction::Remind, $now, null);
    }

    /**
     * Cible de l'escalade : point focal national (garde, puis fiche), sauf s'il est déjà le contact routé ; sinon
     * l'équipe — admins et pair·es éducateur·rices (OnCallRoster::escalationTeam : tous les admins et tous les pair·es, D-MOB-10).
     *
     * @return array{0: DirectoryContact|null, 1: list<int>}
     */
    private function target(Alert $alert, CarbonImmutable $now): array
    {
        $national = $this->roster->nationalFocal($now);

        // L'équipe (admins et pair·es) est prévenue dans les deux cas : le point focal national s'y ajoute, il ne la remplace pas.
        $team = $this->adminIds($now);

        if ($national !== null && (int) $national->getKey() !== (int) $alert->routed_to) {
            return [$national, $team];
        }

        return [null, $team];
    }

    /**
     * @return list<int>
     */
    private function adminIds(CarbonImmutable $now): array
    {
        return $this->roster->escalationTeam($now)->map(static fn (User $user): int => (int) $user->getKey())->values()->all();
    }

    private function report(EscalationRunResult $result, Alert $alert, EscalationAction $action, CarbonImmutable $now, ?DirectoryContact $target): void
    {
        $result->actions[] = [
            'code' => $alert->displayCode(),
            'action' => $action->value,
            'type' => $alert->type->value,
            'minutes' => self::subject($alert)->minutesSinceCreation($now),
            'target' => match (true) {
                $action === EscalationAction::Remind => $alert->routed_to === null ? 'admins' : '#'.$alert->routed_to,
                $target !== null => '#'.$target->getKey(),
                default => 'admins',
            },
        ];
    }

    private static function subject(Alert $alert): EscalationSubject
    {
        $at = static fn (?\DateTimeInterface $date): ?DateTimeImmutable => $date === null ? null : DateTimeImmutable::createFromInterface($date);

        return new EscalationSubject(
            type: $alert->type,
            createdAt: $at($alert->created_at) ?? new DateTimeImmutable,
            acknowledgedAt: $at($alert->acknowledged_at),
            cancelledAt: $at($alert->cancelled_at),
            safeAt: $at($alert->safe_at),
            escalatedAt: $at($alert->escalated_at),
            remindedAt: $at($alert->reminded_at),
            isTerminal: $alert->isTerminal(),
        );
    }
}
