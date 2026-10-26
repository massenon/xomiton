<?php

declare(strict_types=1);

namespace App\Application\Admin\Gateways;

use App\Application\Gateways\ManualPublisher;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\CccKind;
use App\Enums\GatewayDeliveryStatus;
use App\Enums\OutboundKind;
use App\Enums\OutboundStatus;
use App\Models\CccTool;
use App\Models\GatewayTemplate;
use App\Models\OutboundDelivery;
use App\Models\OutboundPost;
use App\Models\SocialChannel;
use App\Models\User;
use App\Support\Audit;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Passerelles de l'administration (P12-08, D4-bis, ADR-15 ; permissions `gateways.connect` pour les comptes, `gateways.publish`
 * pour la file) : relier ou détacher le compte **de l'organisation** d'un réseau, planifier une publication (BD SSR, message
 * clé, texte personnalisé de 500 caractères au plus) sur les réseaux connectés, la marquer publiée à la main, l'annuler ou la
 * retirer. Aucun connecteur réel dans ce lot (P14-04) : tout passe par ManualPublisher (texte prêt à copier, publication à la
 * main). Le jeton d'un réseau à API est enregistré chiffré (cast `encrypted` de SocialChannel), n'est jamais relu par l'écran
 * ni écrit au journal. Aucune donnée de membre n'est jamais transmise.
 */
final class ManageGateways
{
    public const HANDLE_PATTERN = '/^@?[\w.\-]{3,40}$/u';

    public const TOKEN_MAX = 1000;

    /**
     * @throws ContentRefused
     */
    public function connect(SocialChannel $channel, string $account, ?string $token, User $actor): SocialChannel
    {
        $account = trim($account);

        if (preg_match(self::HANDLE_PATTERN, $account) !== 1) {
            throw new ContentRefused('handle_invalid');
        }

        $attributes = [
            'is_connected' => true,
            'account_handle' => '@'.ltrim($account, '@'),
            'connected_at' => now(),
            'connected_by' => $actor->getKey(),
            'last_error' => null,
        ];

        if ($channel->supports_api && $token !== null && trim($token) !== '') {
            $attributes['access_token'] = trim($token);
        }

        $channel->forceFill($attributes)->save();
        Audit::log('gateway.connected', $channel, $channel->label, $actor);

        return $channel;
    }

    /**
     * @throws ContentRefused si des publications planifiées visent encore ce réseau
     */
    public function disconnect(SocialChannel $channel, User $actor): SocialChannel
    {
        $pending = OutboundDelivery::query()->where('social_channel_id', $channel->getKey())->pending()
            ->whereHas('post', fn ($post) => $post->scheduled())->count();

        if ($pending > 0) {
            throw new ContentRefused('channel_in_use', ['count' => $pending]);
        }

        $channel->forceFill([
            'is_connected' => false,
            'account_handle' => null,
            'access_token' => null,
            'refresh_token' => null,
            'token_expires_at' => null,
            'external_id' => null,
            'connected_at' => null,
            'connected_by' => null,
            'last_error' => null,
        ])->save();
        Audit::log('gateway.disconnected', $channel, $channel->label, $actor);

        return $channel;
    }

    /**
     * Planifie une publication. `$data` : kind, reference (clé de BD ou identifiant de modèle), message, channel_ids,
     * scheduled_at (instant UTC, dans le futur).
     *
     * @param  array{kind: string, reference: ?string, message: ?string, channel_ids: list<int>, scheduled_at: \Carbon\CarbonInterface}  $data
     *
     * @throws ContentRefused
     */
    public function schedule(array $data, User $actor): OutboundPost
    {
        $kind = OutboundKind::from($data['kind']);
        $message = trim((string) ($data['message'] ?? ''));
        $reference = null;

        $channels = SocialChannel::query()->connected()->whereIn('id', $data['channel_ids'])->get();

        if ($channels->isEmpty()) {
            throw new ContentRefused('no_network');
        }

        if ($data['scheduled_at']->isPast()) {
            throw new ContentRefused('date_past');
        }

        if ($kind === OutboundKind::Bd) {
            $tool = CccTool::query()->published()->where('key', (string) $data['reference'])->where('kind', CccKind::Comic)->first();

            if ($tool === null) {
                throw new ContentRefused('reference_missing');
            }

            $reference = (string) $tool->key;
            $message = $message !== '' ? $message : (string) $tool->title;
        } elseif ($kind === OutboundKind::Template) {
            $template = GatewayTemplate::query()->active()->find((int) $data['reference']);

            if ($template === null) {
                throw new ContentRefused('reference_missing');
            }

            $reference = (string) $template->getKey();
            $message = $message !== '' ? $message : (string) $template->body;
        } elseif (mb_strlen($message) < OutboundPost::MESSAGE_MIN_LENGTH) {
            throw new ContentRefused('message_short', ['min' => OutboundPost::MESSAGE_MIN_LENGTH]);
        }

        if (mb_strlen($message) > OutboundPost::MESSAGE_MAX_LENGTH) {
            throw new ContentRefused('message_long', ['max' => OutboundPost::MESSAGE_MAX_LENGTH]);
        }

        /** @var OutboundPost $post */
        $post = DB::transaction(function () use ($kind, $reference, $message, $data, $channels, $actor): OutboundPost {
            $post = OutboundPost::query()->create([
                'kind' => $kind,
                'reference' => $reference,
                'message' => $message,
                'scheduled_at' => $data['scheduled_at'],
                'status' => OutboundStatus::Scheduled,
                'created_by' => $actor->getKey(),
            ]);

            foreach ($channels as $channel) {
                $post->deliveries()->create(['social_channel_id' => $channel->getKey(), 'status' => GatewayDeliveryStatus::Pending]);
            }

            return $post;
        });

        Audit::log('gateway.post_scheduled', $post, $channels->pluck('label')->implode(', '), $actor);

        return $post;
    }

    /**
     * Texte à publier sur ce réseau (connecteur choisi par SocialPublishers : prêt à copier).
     */
    public function copyText(OutboundDelivery $delivery): string
    {
        $post = $delivery->post;

        // Jamais publish() ici : « copier » ne doit pas envoyer au réseau (connecteur à API).
        return $post === null ? '' : ManualPublisher::copyText($post);
    }

    /**
     * Marque une remise comme publiée à la main (statut `manuel`) ; la publication passe à « publiée » quand toutes ses
     * remises sont livrées.
     *
     * @throws ContentRefused
     */
    public function markDelivered(OutboundDelivery $delivery, User $actor): OutboundDelivery
    {
        $post = $delivery->post;

        if ($post === null || $post->status === OutboundStatus::Cancelled || $delivery->status->isDelivered()) {
            throw new ContentRefused('delivery_closed');
        }

        DB::transaction(function () use ($delivery, $post, $actor): void {
            $delivery->forceFill([
                'status' => GatewayDeliveryStatus::Manual,
                'published_at' => now(),
                'marked_by' => $actor->getKey(),
                'error' => null,
            ])->save();

            $open = $post->deliveries()->whereNotIn('status', [GatewayDeliveryStatus::Published->value, GatewayDeliveryStatus::Manual->value])->exists();

            if (! $open) {
                $post->forceFill(['status' => OutboundStatus::Published, 'published_at' => now()])->save();
            }
        });

        Audit::log('gateway.delivery_marked', $delivery, null, $actor);

        return $delivery;
    }

    /**
     * @throws ContentRefused
     */
    public function cancel(OutboundPost $post, User $actor): OutboundPost
    {
        if (! $post->isScheduled()) {
            throw new ContentRefused('post_closed');
        }

        $post->forceFill(['status' => OutboundStatus::Cancelled])->save();
        Audit::log('gateway.post_cancelled', $post, null, $actor);

        return $post;
    }

    /**
     * Retire de la file une publication qui n'est pas partie (une publication publiée reste dans l'historique).
     *
     * @throws ContentRefused
     */
    public function remove(OutboundPost $post, User $actor): void
    {
        if ($post->status === OutboundStatus::Published) {
            throw new ContentRefused('post_published');
        }

        $post->delete();
        Audit::log('gateway.post_removed', $post, null, $actor);
    }

    /** Titre lisible de la référence d'une publication (BD, modèle) pour la file. */
    public static function referenceLabel(OutboundPost $post): string
    {
        return match ($post->kind) {
            OutboundKind::Bd => (string) (CccTool::query()->where('key', (string) $post->reference)->value('title') ?? $post->reference),
            OutboundKind::Template => (string) (GatewayTemplate::query()->whereKey((int) $post->reference)->value('title') ?? Str::limit((string) $post->message, 40)),
            default => (string) __('admin_gateways.queue.custom'),
        };
    }
}
