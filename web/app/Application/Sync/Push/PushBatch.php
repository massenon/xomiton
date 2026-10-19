<?php

declare(strict_types=1);

namespace App\Application\Sync\Push;

use App\Application\Cases\PeerSpace;
use App\Application\Platform\MaintenanceStatus;
use App\Application\Sync\Pull\Collections\CasesCollection;
use App\Application\Sync\Push\Handlers\CaseItemHandler;
use App\Application\Sync\Push\Handlers\MessageItemHandler;
use App\Application\Sync\Push\Handlers\ReportItemHandler;
use App\Application\Sync\Push\Handlers\SosItemHandler;
use App\Domain\Shared\Exceptions\DomainException;
use App\Domain\Sync\SyncItemType;
use App\Models\CaseRecord;
use App\Models\SyncConflict;
use App\Models\SyncOutboxReceipt;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * `POST sync/push` (P9-06, SOFTWARE_ARCHITECTURE.md §5, ADR-32) : lot de 50 éléments au plus, traités un par un — un
 * élément en erreur n'arrête jamais le lot. Le rejeu du **même lot** (même `Idempotency-Key`) est rendu tel quel par
 * le middleware `idempotent` (réponse d'origine). Chaque **élément** est dédoublonné par son uuid : reçu
 * `sync_outbox_receipts` (appareil + uuid, empreinte du contenu) puis clé métier unique (`alerts.idempotency_key`,
 * `messages.client_uuid`, `cases.client_uuid`) ; un rejeu rend `duplicate` avec le même `server_id`, jamais un doublon
 * — sauf une modification de dossier conclue en conflit, rendue telle qu'à l'origine (409, conflits relus dans
 * `sync_conflicts`, état courant du dossier). Même uuid avec un autre contenu : refus `uuid_reused`. Un reçu `rejected`
 * n'arrête pas un nouvel essai (référentiel corrigé entre-temps). Ordre : SOS d'abord, puis signalements, collecte,
 * messages ; réponse dans l'ordre du lot.
 *
 * Gardes par élément, sans reçu (l'appareil renvoie l'élément dans un prochain lot) :
 *  - maintenance (RG-14) pour un compte hors administration : seuls SOS et signalements sont traités (RG-01), les
 *    autres reçoivent 503 `retry_later` ;
 *  - quotas des routes dédiées (ItemLimiter : `messages_write`, `report`) : 429 `too_many_requests` ; SOS jamais
 *    limité ;
 *  - budget de temps du lot (`xomiton.sync.push_time_budget_seconds`, 20 s, sous les 30 s de D7) : au-delà, les
 *    éléments restants reçoivent 503 `retry_later` (le premier élément est toujours traité).
 */
final class PushBatch
{
    /** Résultats qui valent réception définitive : un rejeu rend `duplicate` sans retraiter. */
    private const SETTLED = [
        SyncOutboxReceipt::RESULT_CREATED,
        SyncOutboxReceipt::RESULT_UPDATED,
        SyncOutboxReceipt::RESULT_DUPLICATE,
        SyncOutboxReceipt::RESULT_CONFLICT,
    ];

    /** Types traités pendant la maintenance (RG-01 : SOS et signalement toujours possibles). */
    private const DURING_MAINTENANCE = [SyncItemType::Sos, SyncItemType::Report];

    /** Fenêtre (secondes) avant le reçu dans laquelle relire les conflits d'un élément rejoué. */
    private const REPLAY_WINDOW_SECONDS = 120;

    public function __construct(
        private readonly SosItemHandler $sos,
        private readonly ReportItemHandler $report,
        private readonly CaseItemHandler $case,
        private readonly MessageItemHandler $message,
    ) {}

    /**
     * @param  list<PushItem>  $items
     * @return list<ItemOutcome> dans l'ordre du lot
     */
    public function __invoke(PushContext $context, array $items): array
    {
        $ordered = $items;
        usort($ordered, static fn (PushItem $a, PushItem $b): int => [self::priority($a), $a->index] <=> [self::priority($b), $b->index]);

        $deadline = hrtime(true) + (int) (max(0.0, (float) config('xomiton.sync.push_time_budget_seconds', 20)) * 1_000_000_000);
        $maintenance = MaintenanceStatus::active() && ! MaintenanceStatus::isExempt($context->user);
        $outcomes = [];
        $processed = 0;

        foreach ($ordered as $item) {
            if ($processed > 0 && hrtime(true) >= $deadline) {
                $outcomes[$item->index] = ItemOutcome::retry($item);

                continue;
            }

            $outcomes[$item->index] = $this->one($context, $item, $maintenance);
            $processed++;
        }

        ksort($outcomes);

        return array_values($outcomes);
    }

    private function one(PushContext $context, PushItem $item, bool $maintenance): ItemOutcome
    {
        $type = SyncItemType::tryFrom($item->type);

        if ($type === null) {
            return ItemOutcome::rejected($item, ItemRejected::unknownType());
        }

        $hash = $item->hash();
        $receipt = SyncOutboxReceipt::findForReplay($context->deviceId(), strtolower($item->uuid));

        if ($receipt !== null && ! hash_equals($receipt->payload_hash, $hash)) {
            return ItemOutcome::rejected($item, ItemRejected::uuidReused());
        }

        if ($receipt !== null && in_array($receipt->result, self::SETTLED, true)) {
            return $this->replay($context, $item, $receipt);
        }

        if ($maintenance && ! in_array($type, self::DURING_MAINTENANCE, true)) {
            return ItemOutcome::retry($item, (string) __('sync.items.maintenance'));
        }

        if (! $context->limiter->attempt($type)) {
            return ItemOutcome::throttled($item);
        }

        try {
            $outcome = $this->handler($type)->handle($item, $context);
        } catch (ItemRejected $rejection) {
            $outcome = ItemOutcome::rejected($item, $rejection);
        } catch (DomainException $exception) {
            $outcome = ItemOutcome::rejected($item, ItemRejected::fromDomain($exception));
        } catch (Throwable $exception) {
            // Jamais le contenu de l'élément (récit, notes) dans le journal : type et uuid seulement.
            Log::error('sync.push_item_failed', ['type' => $item->type, 'uuid' => $item->uuid, 'error' => $exception::class.': '.$exception->getMessage()]);
            report($exception);
            $outcome = ItemOutcome::retry($item);
        }

        if ($outcome->storable) {
            $this->remember($context, $item, $type, $outcome, $hash, $receipt);
        }

        return $outcome;
    }

    /**
     * Élément déjà reçu : `duplicate` avec le même sujet ; modification de dossier : état courant du dossier, et pour un
     * conflit la réponse d'origine (409, conflits relus dans `sync_conflicts` ; `applied` / `kept` ne sont pas gardés).
     * Dossier plus visible du compte (réattribué, espace pair perdu) : ni identifiant, ni référence, ni état.
     */
    private function replay(PushContext $context, PushItem $item, SyncOutboxReceipt $receipt): ItemOutcome
    {
        $subject = $receipt->subject_type === null ? null : $receipt->subject;

        if (! $item->isUpdate() || ! $subject instanceof CaseRecord) {
            return ItemOutcome::duplicate($item, $subject);
        }

        $visible = PeerSpace::allows($context->user) && Gate::forUser($context->user)->allows('view', $subject);
        $record = $visible ? CasesCollection::row($subject) : null;

        if ($receipt->result !== SyncOutboxReceipt::RESULT_CONFLICT) {
            return ItemOutcome::duplicate($item, $subject, hide: ! $visible, record: $record);
        }

        return ItemOutcome::conflict($item, $subject, $this->conflictsOf($context, $item, $receipt, $subject), [], [], $record, hide: ! $visible);
    }

    /**
     * Conflits enregistrés par l'élément : les plus récents par champ modifié, pour cet appareil et ce dossier, écrits
     * juste avant le reçu.
     *
     * @return list<array{field: string, conflict_id: int}>
     */
    private function conflictsOf(PushContext $context, PushItem $item, SyncOutboxReceipt $receipt, CaseRecord $case): array
    {
        $changes = $item->payload['changes'] ?? [];
        $fields = is_array($changes) ? array_map('strval', array_keys($changes)) : [];
        $at = $receipt->created_at ?? now();

        return SyncConflict::query()
            ->where('device_id', $context->deviceId())
            ->where('subject_type', $case->getMorphClass())
            ->where('subject_id', $case->getKey())
            ->whereIn('field', $fields)
            ->whereBetween('created_at', [$at->copy()->subSeconds(self::REPLAY_WINDOW_SECONDS), $at->copy()->addSecond()])
            ->orderByDesc('id')
            ->get(['id', 'field'])
            ->unique('field')
            ->sortBy('id')
            ->map(static fn (SyncConflict $conflict): array => ['field' => (string) $conflict->field, 'conflict_id' => (int) $conflict->getKey()])
            ->values()
            ->all();
    }

    private function handler(SyncItemType $type): ItemHandler
    {
        return match ($type) {
            SyncItemType::Sos => $this->sos,
            SyncItemType::Report => $this->report,
            SyncItemType::Case => $this->case,
            SyncItemType::Message => $this->message,
        };
    }

    private static function priority(PushItem $item): int
    {
        return SyncItemType::tryFrom($item->type)?->priority() ?? PHP_INT_MAX;
    }

    private function remember(PushContext $context, PushItem $item, SyncItemType $type, ItemOutcome $outcome, string $hash, ?SyncOutboxReceipt $receipt): void
    {
        $subject = $outcome->receiptSubject();
        $attributes = [
            'kind' => $type->receiptKind(),
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'result' => $outcome->result,
            'payload_hash' => $hash,
        ];

        try {
            if ($receipt !== null) {
                // Nouvel essai d'un élément refusé : le reçu date du dernier résultat (rejeu d'un conflit, rétention).
                $receipt->forceFill($attributes + ['created_at' => now()])->save();

                return;
            }

            SyncOutboxReceipt::query()->create(['device_id' => $context->deviceId(), 'idempotency_key' => strtolower($item->uuid)] + $attributes);
        } catch (UniqueConstraintViolationException) {
            // Envoi concurrent du même élément : le premier reçu fait foi.
        }
    }
}
