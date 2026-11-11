<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Enums\GatewayDeliveryStatus;
use App\Enums\OutboundStatus;
use App\Models\AuditLog;
use App\Models\OutboundDelivery;
use App\Models\OutboundPost;
use App\Support\Audit;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Passage de `gateways:publish` (P14-04, ADR-15) : par lots (`xomiton.gateways.batch_size`, 20), chaque remise échue
 * (publication planifiée dont l'heure est passée, réseau connecté) est confiée au connecteur du réseau (SocialPublishers).
 *  - publiée : statut `publie`, identifiants chez le réseau ;
 *  - refus ou panne : statut `echec`, tentative comptée, **rejeu** aux passages suivants jusqu'à `max_attempts` (5) ;
 *  - « à publier à la main » (réseau sans API, jeton absent ou expiré, pilote `log`) : la remise reste en attente et est
 *    marquée `manuel` une fois pour toutes, pour ne pas être reprise ; une personne publie puis la marque publiée.
 * Chaque issue laisse une ligne d'audit sans le texte, sans jeton ni identifiant de membre. Aucune donnée de membre.
 */
final class PublishDueDeliveries
{
    /** Marqueur de `outbound_deliveries.error` : remise laissée à la publication manuelle assistée. */
    public const MANUAL_MARKER = 'manuel';

    public function __construct(private readonly SocialPublishers $publishers) {}

    /**
     * @return array{published: int, failed: int, manual: int}
     */
    public function run(?int $limit = null): array
    {
        $limit ??= max(1, (int) config('xomiton.gateways.batch_size', 20));
        $result = ['published' => 0, 'failed' => 0, 'manual' => 0];

        $deliveries = $this->dueQuery()->with(['post', 'channel'])->orderBy('id')->limit($limit)->get();

        foreach ($deliveries as $delivery) {
            $post = $delivery->post;
            $channel = $delivery->channel;

            if ($post === null || $channel === null) {
                continue;
            }

            $outcome = $this->publishers->for($channel)->publish($post, $channel);

            if ($outcome->status === GatewayDeliveryStatus::Published) {
                $delivery->forceFill([
                    'status' => GatewayDeliveryStatus::Published,
                    'external_id' => $outcome->externalId,
                    'external_url' => $outcome->externalUrl,
                    'published_at' => now(),
                    'error' => null,
                    'attempts' => $delivery->attempts + 1,
                ])->save();
                $result['published']++;
                $this->audit('gateway.delivery_published', $delivery, $channel->network->value);
            } elseif ($outcome->status === GatewayDeliveryStatus::Failed) {
                $delivery->forceFill([
                    'status' => GatewayDeliveryStatus::Failed,
                    'error' => mb_substr((string) $outcome->error, 0, 255),
                    'attempts' => $delivery->attempts + 1,
                ])->save();
                $result['failed']++;
                $this->audit('gateway.delivery_failed', $delivery, $channel->network->value.' ; '.$outcome->error);
            } else {
                $delivery->forceFill(['status' => GatewayDeliveryStatus::Pending, 'error' => self::MANUAL_MARKER])->save();
                $result['manual']++;
                $this->audit('gateway.delivery_manual', $delivery, $channel->network->value);
            }

            $this->closePostIfDone($post);
        }

        return $result;
    }

    /** @return Builder<OutboundDelivery> */
    private function dueQuery(): Builder
    {
        return OutboundDelivery::query()
            ->whereHas('post', fn (Builder $post) => $post->due())
            ->whereHas('channel', fn (Builder $channel) => $channel->connected())
            ->where(function (Builder $query): void {
                $query->where(fn (Builder $new) => $new->where('status', GatewayDeliveryStatus::Pending->value)->whereNull('error'))
                    ->orWhere(fn (Builder $retry) => $retry->retryable()->where('status', GatewayDeliveryStatus::Failed->value));
            });
    }

    /** Publication « publiée » quand toutes ses remises le sont ; « en échec » quand plus aucune ne peut être rejouée. */
    private function closePostIfDone(OutboundPost $post): void
    {
        DB::transaction(function () use ($post): void {
            $deliveries = $post->deliveries()->get();

            if ($deliveries->isNotEmpty() && $deliveries->every(fn (OutboundDelivery $d): bool => $d->isDelivered())) {
                $post->forceFill(['status' => OutboundStatus::Published, 'published_at' => now()])->save();
            } elseif ($deliveries->isNotEmpty() && $deliveries->every(fn (OutboundDelivery $d): bool => $d->isDelivered() || ($d->status === GatewayDeliveryStatus::Failed && ! $d->canRetry()))) {
                $post->forceFill(['status' => OutboundStatus::Failed])->save();
            }
        });
    }

    private function audit(string $action, OutboundDelivery $delivery, string $detail): void
    {
        Audit::logWithoutRequest($action, $delivery, $detail, AuditLog::ACTOR_SYSTEM);
    }
}
