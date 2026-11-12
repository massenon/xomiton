<?php

declare(strict_types=1);

namespace App\Application\Admin\Gateways;

use App\Application\Gateways\ManualPublisher;
use App\Application\Gateways\OutboundContent;
use App\Application\Gateways\PublishDueDeliveries;
use App\Application\Gateways\Transport\GatewayError;
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
use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection as EloquentCollection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Lang;
use Illuminate\Support\Str;

/**
 * Passerelles de l'administration (P12-08, D4-bis, ADR-15 ; permissions `gateways.connect` pour les comptes, `gateways.publish`
 * pour la file) : relier ou détacher le compte **de l'organisation** d'un réseau, planifier une publication (BD SSR, message
 * clé, texte personnalisé de 500 caractères au plus) sur les réseaux connectés, la marquer publiée à la main, l'annuler ou la
 * retirer. P19-11 : calendrier de diffusion des BD (une BD, ou en lot pour toutes les BD publiques pas encore diffusées sur
 * un réseau), jamais deux fois la même BD sur le même canal sauf republication explicite ; les connecteurs à API publient
 * avec le pilote `http`, les autres réseaux passent par ManualPublisher (texte et lien prêts à copier). Le jeton d'un réseau
 * à API est enregistré chiffré (cast `encrypted` de SocialChannel), n'est jamais relu par l'écran ni écrit au journal.
 * Aucune donnée de membre n'est jamais transmise.
 */
final class ManageGateways
{
    public const HANDLE_PATTERN = '/^@?[\w.\-]{3,40}$/u';

    public const TOKEN_MAX = 1000;

    /** Identifiant de la page Facebook (numérique) ou du canal Telegram (`-100…` ou `@canal`), P19-11. */
    public const EXTERNAL_ID_PATTERN = '/^(-?\d{1,20}|@[A-Za-z0-9_]{4,64})$/';

    /** Calendrier en lot : écart entre deux BD, en jours. */
    public const SERIES_MIN_DAYS = 1;

    public const SERIES_MAX_DAYS = 60;

    /**
     * @throws ContentRefused
     */
    public function connect(SocialChannel $channel, string $account, ?string $token, User $actor, ?string $externalId = null): SocialChannel
    {
        $account = trim($account);
        $externalId = trim((string) $externalId);

        if (preg_match(self::HANDLE_PATTERN, $account) !== 1) {
            throw new ContentRefused('handle_invalid');
        }

        if ($externalId !== '' && preg_match(self::EXTERNAL_ID_PATTERN, $externalId) !== 1) {
            throw new ContentRefused('external_id_invalid');
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

        if ($channel->supports_api && $externalId !== '') {
            $attributes['external_id'] = $externalId;
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
     * scheduled_at (instant UTC, dans le futur), republish (BD : republication explicite, P19-11).
     *
     * @param  array{kind: string, reference: ?string, message: ?string, channel_ids: list<int>, scheduled_at: CarbonInterface, republish?: bool}  $data
     *
     * @throws ContentRefused
     */
    public function schedule(array $data, User $actor): OutboundPost
    {
        $kind = OutboundKind::from($data['kind']);
        $message = trim((string) ($data['message'] ?? ''));
        $reference = null;
        $republish = $kind === OutboundKind::Bd && (bool) ($data['republish'] ?? false);

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
            $message = $message !== '' ? $message : self::comicMessage($tool);
            $shared = $channels->whereIn('id', self::sharedChannelIds($reference));

            if (! $republish && $shared->isNotEmpty()) {
                throw new ContentRefused('already_shared', ['networks' => $shared->pluck('label')->implode(', ')]);
            }
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

        $post = $this->createPost($kind, $reference, $message, $data['scheduled_at'], $channels, $actor, $republish);
        Audit::log('gateway.post_scheduled', $post, $channels->pluck('label')->implode(', '), $actor);

        return $post;
    }

    /**
     * Calendrier de diffusion en lot (P19-11) : les BD **publiques** pas encore planifiées ni diffusées sur au moins un des
     * réseaux choisis, dans l'ordre de `/bd`, une tous les `every_days` jours à partir de `starts_at` (même heure locale).
     * Chaque BD ne vise que les réseaux où elle manque. Rien n'est créé si aucune BD ne reste à diffuser.
     *
     * @param  array{channel_ids: list<int>, starts_at: CarbonInterface, every_days: int}  $data
     * @return list<OutboundPost>
     *
     * @throws ContentRefused
     */
    public function scheduleComicSeries(array $data, User $actor): array
    {
        $plan = $this->planComicSeries($data);

        if ($plan === []) {
            throw new ContentRefused('series_empty');
        }

        /** @var list<OutboundPost> $posts */
        $posts = DB::transaction(function () use ($plan, $actor): array {
            $posts = [];

            foreach ($plan as $slot) {
                $posts[] = $this->createPost(OutboundKind::Bd, (string) $slot['tool']->key, self::comicMessage($slot['tool']), $slot['at'], $slot['channels'], $actor, false);
            }

            return $posts;
        });

        Audit::log('gateway.series_scheduled', $posts[0], count($posts).' ; '.collect($plan)->flatMap(fn (array $slot) => $slot['channels']->pluck('label'))->unique()->implode(', '), $actor);

        return $posts;
    }

    /**
     * Aperçu du calendrier en lot (mêmes règles que scheduleComicSeries, sans rien écrire).
     *
     * @param  array{channel_ids: list<int>, starts_at: CarbonInterface, every_days: int}  $data
     * @return list<array{tool: CccTool, at: CarbonImmutable, channels: EloquentCollection<int, SocialChannel>}>
     *
     * @throws ContentRefused
     */
    public function planComicSeries(array $data): array
    {
        $channels = SocialChannel::query()->connected()->whereIn('id', $data['channel_ids'])->orderBy('id')->get();
        $every = $data['every_days'];

        if ($channels->isEmpty()) {
            throw new ContentRefused('no_network');
        }

        if ($data['starts_at']->isPast()) {
            throw new ContentRefused('date_past');
        }

        if ($every < self::SERIES_MIN_DAYS || $every > self::SERIES_MAX_DAYS) {
            throw new ContentRefused('series_interval', ['min' => self::SERIES_MIN_DAYS, 'max' => self::SERIES_MAX_DAYS]);
        }

        $timezone = (string) config('xomiton.display_timezone', 'UTC');
        $start = CarbonImmutable::instance($data['starts_at'])->setTimezone($timezone);
        $plan = [];

        foreach (CccTool::query()->publicComics()->ordered()->get() as $tool) {
            $shared = self::sharedChannelIds((string) $tool->key);
            $missing = $channels->reject(fn (SocialChannel $channel): bool => in_array((int) $channel->getKey(), $shared, true))->values();

            if ($missing->isEmpty()) {
                continue;
            }

            // Même heure locale (Bénin) à chaque échéance, quel que soit le décalage.
            $plan[] = ['tool' => $tool, 'at' => $start->addDays(count($plan) * $every)->utc(), 'channels' => $missing];
        }

        return $plan;
    }

    /**
     * Réseaux sur lesquels la BD est déjà planifiée (publication en attente) ou déjà en ligne (API ou à la main).
     * Une publication annulée, retirée ou entièrement en échec ne compte pas : la BD peut être replanifiée.
     *
     * @return array<int, int>
     */
    public static function sharedChannelIds(string $key): array
    {
        return OutboundDelivery::query()
            ->whereHas('post', fn (Builder $post) => $post->ofKind(OutboundKind::Bd)->where('reference', $key))
            ->where(fn (Builder $query) => $query->delivered()->orWhereHas('post', fn (Builder $post) => $post->scheduled()))
            ->pluck('social_channel_id')->map(fn (mixed $id): int => (int) $id)->unique()->values()->all();
    }

    /** Message proposé pour une BD : son texte de partage (`share.text`), sinon titre + résumé, 500 caractères au plus. */
    public static function comicMessage(CccTool $tool): string
    {
        return Str::limit(app(OutboundContent::class)->defaultComicMessage($tool), OutboundPost::MESSAGE_MAX_LENGTH - 1, '…');
    }

    /** @param  EloquentCollection<int, SocialChannel>  $channels */
    private function createPost(OutboundKind $kind, ?string $reference, string $message, CarbonInterface $at, EloquentCollection $channels, User $actor, bool $republish): OutboundPost
    {
        /** @var OutboundPost $post */
        $post = DB::transaction(function () use ($kind, $reference, $message, $at, $channels, $actor, $republish): OutboundPost {
            $post = OutboundPost::query()->create([
                'kind' => $kind,
                'reference' => $reference,
                'message' => $message,
                'scheduled_at' => $at,
                'status' => OutboundStatus::Scheduled,
                'created_by' => $actor->getKey(),
                'is_republish' => $republish,
            ]);

            foreach ($channels as $channel) {
                $post->deliveries()->create(['social_channel_id' => $channel->getKey(), 'status' => GatewayDeliveryStatus::Pending]);
            }

            return $post;
        });

        return $post;
    }

    /**
     * Texte à publier sur ce réseau (connecteur choisi par SocialPublishers : prêt à copier).
     */
    public function copyText(OutboundDelivery $delivery): string
    {
        $post = $delivery->loadMissing('post')->post;

        $delivery->loadMissing('channel');

        // Jamais publish() ici : « copier » ne doit pas envoyer au réseau (connecteur à API).
        if ($post === null) {
            return '';
        }

        return $delivery->channel === null ? ManualPublisher::copyText($post) : ManualPublisher::copyTextFor($post, $delivery->channel);
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

    /**
     * Erreur lisible d'une remise pour la file (P19-11) : le code traduit (`admin_gateways.delivery_errors`) puis la
     * description du réseau ; null sans erreur ou pour une remise laissée à la publication manuelle.
     */
    public static function deliveryError(OutboundDelivery $delivery): ?string
    {
        $error = trim((string) $delivery->error);

        if ($error === '' || $error === PublishDueDeliveries::MANUAL_MARKER) {
            return null;
        }

        $code = GatewayError::codeOf($error);
        $key = 'admin_gateways.delivery_errors.'.$code;
        $label = Lang::has($key) ? (string) __($key) : (string) __('admin_gateways.delivery_errors.unknown');
        $detail = (string) preg_replace('/^\(HTTP (\d+)\)/', 'HTTP $1', trim(mb_substr($error, mb_strlen($code))));
        $detail = ltrim($detail, ' :');

        return $detail === '' ? $label : $label.' — '.$detail;
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
