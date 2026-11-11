<?php

declare(strict_types=1);

namespace App\Application\Feed;

use App\Application\Community\Events\EventCreateOptions;
use App\Application\Shared\MediaUploader;
use App\Enums\ContentReportReason;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Models\Event;
use App\Models\Poll;
use App\Models\Post;
use App\Models\User;

/**
 * Réglages des modales « Créer une publication » et « Créer un sondage » (P7-04, prototype partials/modals.php),
 * transmis en props Inertia : droits (`posts.create`, `polls.create`), étiquettes (toutes sauf « sondage », imposée au
 * sondage), visibilités, durées et bornes — les mêmes que StorePostRequest (config xomiton.feed, image : collection
 * `images` de MediaUploader), pour que la validation dans le navigateur ne contredise jamais le serveur.
 * Pastille « Événement » (P8-04) : active pour qui peut créer un événement ; elle ouvre la modale « Créer un événement »
 * avec les réglages `event` (App\Application\Community\Events\EventCreateOptions ; null sans ce droit).
 */
final class FeedComposerOptions
{
    public function __construct(private readonly EventCreateOptions $events) {}

    /** Durée proposée par défaut (prototype : « 1 semaine » sélectionnée). */
    public const DEFAULT_DURATION = 7;

    /**
     * @return array{
     *     can_post: bool, can_poll: bool, events_available: bool, event: array<string, mixed>|null,
     *     tags: list<array{value: string, label: string}>, visibilities: list<array{value: string, label: string}>,
     *     durations: list<array{value: int, label: string}>, default_duration: int,
     *     limits: array{body_max: int, question_min: int, question_max: int, options_min: int, options_max: int, option_max: int, image_max_kb: int, image_types: list<string>}
     * }
     */
    public function for(User $user): array
    {
        $canEvent = $user->can('create', Event::class);

        return [
            'can_post' => $user->can('create', Post::class),
            'can_poll' => $user->can('createPoll', Post::class),
            'events_available' => $canEvent,
            'event' => $canEvent ? $this->events->for($user) : null,
            'tags' => $this->tags(),
            'visibilities' => array_map(static fn (PostVisibility $visibility): array => [
                'value' => $visibility->value,
                'label' => (string) __('feed.create.visibility_options.'.$visibility->value),
            ], PostVisibility::cases()),
            'durations' => $this->durations(),
            'default_duration' => self::DEFAULT_DURATION,
            'limits' => [
                'body_max' => (int) config('xomiton.feed.post_max_length', Post::BODY_MAX_LENGTH),
                'question_min' => (int) config('xomiton.feed.poll_question_min', 5),
                'question_max' => (int) config('xomiton.feed.poll_question_max', 200),
                'options_min' => Poll::MIN_OPTIONS,
                'options_max' => Poll::MAX_OPTIONS,
                'option_max' => (int) config('xomiton.feed.poll_option_max_length', 80),
                'image_max_kb' => MediaUploader::maxKb('images'),
                'image_types' => array_values(MediaUploader::allowedMimes('images')),
            ],
        ];
    }

    /**
     * Motifs de la modale « Signaler cette publication / ce commentaire » (P7-05) : ContentReportReason::selectable()
     * (le motif automatique est exclu), libellés enums/feed.report_reason ; transmis en props `report_reasons` par les
     * pages qui affichent des publications, pour que la liste du navigateur suive toujours l'enum.
     *
     * @return list<array{value: string, label: string}>
     */
    public function reportReasons(): array
    {
        return array_map(static fn (string $reason): array => [
            'value' => $reason,
            'label' => ContentReportReason::from($reason)->label(),
        ], ContentReportReason::selectable());
    }

    /**
     * @return list<array{value: string, label: string}>
     */
    private function tags(): array
    {
        $tags = array_filter(PostTag::cases(), static fn (PostTag $tag): bool => $tag !== PostTag::Poll);

        return array_values(array_map(static fn (PostTag $tag): array => [
            'value' => $tag->value,
            'label' => $tag === PostTag::Violence ? (string) __('feed.create.tag_violences') : $tag->label(),
        ], $tags));
    }

    /**
     * Durées de config xomiton.feed.poll_durations_days (null = sans fin, envoyé 0), dans l'ordre du prototype.
     *
     * @return list<array{value: int, label: string}>
     */
    private function durations(): array
    {
        $days = array_map(static fn (mixed $value): int => is_int($value) ? $value : 0, (array) config('xomiton.feed.poll_durations_days', []));

        return array_values(array_map(static fn (int $value): array => [
            'value' => $value,
            'label' => (string) __('feed.poll.durations.'.$value),
        ], array_unique($days)));
    }
}
