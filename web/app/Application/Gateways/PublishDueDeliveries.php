<?php

declare(strict_types=1);

namespace App\Application\Gateways;

use App\Application\Gateways\Transport\GatewayError;
use App\Enums\GatewayDeliveryStatus;
use App\Enums\OutboundKind;
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
 *
 * P19-11 : **idempotence** — une BD déjà en ligne sur un canal (autre publication, API ou à la main) n'y repart jamais,
 * sauf republication explicite (`outbound_posts.is_republish`) : la remise est close en `echec` `already_published`, sans
 * appel. **Débit** — au plus `xomiton.gateways.per_channel_per_run` publications par l'API et par canal à chaque passage
 * (les suivantes attendent le passage d'après) ; une limite de débit du réseau (`rate_limited`) arrête ce canal pour le
 * passage et ne compte pas de tentative. Un jeton refusé (`unauthorized`) est signalé sur la carte du réseau
 * (`social_channels.last_error`), effacé à la publication suivante réussie.
 */
final class PublishDueDeliveries
{
    /** Marqueur de `outbound_deliveries.error` : remise laissée à la publication manuelle assistée. */
    public const MANUAL_MARKER = 'manuel';

    /** Remise close sans appel : la BD est déjà en ligne sur ce canal (P19-11). */
    public const ALREADY_PUBLISHED = 'already_published';

    public function __construct(private readonly SocialPublishers $publishers) {}

    /**
     * @return array{published: int, failed: int, manual: int}
     */
    public function run(?int $limit = null): array
    {
        $limit ??= max(1, (int) config('xomiton.gateways.batch_size', 20));
        $result = ['published' => 0, 'failed' => 0, 'manual' => 0];

        $deliveries = $this->dueQuery()->with(['post', 'channel'])->orderBy('id')->limit($limit)->get();
        $perChannel = max(1, (int) config('xomiton.gateways.per_channel_per_run', 2));
        /** @var array<int, int> $sent publications par l'API, par canal, dans ce passage */
        $sent = [];
        /** @var array<int, true> $paused canaux arrêtés pour ce passage (limite de débit du réseau) */
        $paused = [];

        foreach ($deliveries as $delivery) {
            $post = $delivery->post;
            $channel = $delivery->channel;

            if ($post === null || $channel === null) {
                continue;
            }

            $channelId = (int) $channel->getKey();
            $network = (string) $channel->getRawOriginal('network');

            if ($this->alreadyPublished($post, $delivery)) {
                $delivery->forceFill([
                    'status' => GatewayDeliveryStatus::Failed,
                    'error' => self::ALREADY_PUBLISHED,
                    'attempts' => OutboundDelivery::maxAttempts(),
                ])->save();
                $result['failed']++;
                $this->audit('gateway.delivery_failed', $delivery, $network.' ; '.self::ALREADY_PUBLISHED);
                $this->closePostIfDone($post);

                continue;
            }

            $publisher = $this->publishers->for($channel);
            $viaApi = ! $publisher instanceof ManualPublisher;

            if ($viaApi && (isset($paused[$channelId]) || ($sent[$channelId] ?? 0) >= $perChannel)) {
                continue; // repris au passage suivant
            }

            $outcome = $publisher->publish($post, $channel);

            if ($viaApi) {
                $sent[$channelId] = ($sent[$channelId] ?? 0) + 1;
            }

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
                $this->audit('gateway.delivery_published', $delivery, $network);

                if ($channel->last_error !== null) {
                    $channel->forceFill(['last_error' => null])->save();
                }
            } elseif ($outcome->status === GatewayDeliveryStatus::Failed) {
                $error = mb_substr((string) $outcome->error, 0, 255);
                $code = GatewayError::codeOf($error);
                $rateLimited = $code === GatewayError::RATE_LIMITED;

                $delivery->forceFill([
                    'status' => GatewayDeliveryStatus::Failed,
                    'error' => $error,
                    // Limite de débit : reprise plus tard sans consommer de tentative.
                    'attempts' => $rateLimited ? $delivery->attempts : $delivery->attempts + 1,
                ])->save();
                $result['failed']++;
                $this->audit('gateway.delivery_failed', $delivery, $network.' ; '.$error);

                if ($rateLimited) {
                    $paused[$channelId] = true;
                }

                if (in_array($code, [GatewayError::UNAUTHORIZED, 'credentials_missing'], true)) {
                    $channel->forceFill(['last_error' => $error])->save();
                }
            } else {
                $delivery->forceFill(['status' => GatewayDeliveryStatus::Pending, 'error' => self::MANUAL_MARKER])->save();
                $result['manual']++;
                $this->audit('gateway.delivery_manual', $delivery, $network);
            }

            $this->closePostIfDone($post);
        }

        return $result;
    }

    /** BD déjà en ligne sur ce canal par une autre publication, hors republication explicite. */
    private function alreadyPublished(OutboundPost $post, OutboundDelivery $delivery): bool
    {
        if (OutboundKind::tryFrom((string) $post->getRawOriginal('kind')) !== OutboundKind::Bd || $post->is_republish) {
            return false;
        }

        return OutboundDelivery::query()
            ->forChannel((int) $delivery->social_channel_id)
            ->delivered()
            ->where('outbound_post_id', '!=', $post->getKey())
            ->whereHas('post', fn (Builder $other) => $other->ofKind(OutboundKind::Bd)->where('reference', (string) $post->reference))
            ->exists();
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
