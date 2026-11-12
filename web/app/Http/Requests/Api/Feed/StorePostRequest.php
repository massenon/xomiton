<?php

declare(strict_types=1);

namespace App\Http\Requests\Api\Feed;

use App\Application\Feed\Data\PollDraft;
use App\Application\Feed\Data\PostDraft;
use App\Application\Feed\FeedMediaLimits;
use App\Enums\PostTag;
use App\Enums\PostVisibility;
use App\Http\Requests\Api\Feed\Concerns\FeedRequest;
use App\Models\Group;
use App\Models\Poll;
use App\Models\Post;
use App\Rules\UploadAllowed;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * POST /api/v1/posts (P7-02, FR-SOC-01, FR-SOC-02 ; prototype post-create.php) — JSON ou multipart (images, vidéo) :
 *  - publication : `{kind?: "post", body, tag?, visibility?, is_anonymous?, images[]?, images_alt[]?, video?,
 *    video_description?}` — texte obligatoire sauf média seul, ≤ 3000 car. ; étiquette par défaut « témoignage », jamais
 *    « sondage » ; `image` / `image_alt` (une seule image) restent acceptés et comptent comme `images[0]` ;
 *  - images : jusqu'à `feed.max_images` (4), `images_alt[i]` aligné sur `images[i]` (≤ 255 car.), ordre conservé ;
 *  - vidéo (Q96) : **une seule**, mp4 ou webm, jamais avec des images ni dans un sondage, poids ≤ FeedMediaLimits::videoMaxKb(),
 *    `video_description` obligatoire (5 à 500 car.) ; la publication part en relecture (202, invisible jusqu'à l'approbation) ;
 *  - sondage : `{kind: "poll", body?, visibility?, is_anonymous?, images[]?, images_alt[]?, poll: {question, options[], multiple?,
 *    results_before_vote?, duration_days?}}` — question 5–200 car., 2 à 6 réponses distinctes (casse et accents
 *    ignorés, réponses vides retirées comme le prototype), ≤ 80 car., durée 1, 3, 7, 14, 30 jours ou 0 / null (sans fin).
 * `posts.create` (403 sinon), `polls.create` pour un sondage ; e-mail confirmé (middleware `verified`, Q12 c).
 * Anonyme : visibilité `public` ou `me` seulement (422 sur `followers`, Q72).
 * Groupe (P8-02) : `group` = ULID d'un groupe où la personne peut publier (membre active ou propriétaire, sinon 422
 * `group` : on ne dit pas si le groupe existe), visibilité `public` seulement (le groupe est l'audience).
 */
final class StorePostRequest extends FormRequest
{
    use FeedRequest;

    public const KIND_POST = 'post';

    public const KIND_POLL = 'poll';

    private ?Group $resolvedGroup = null;

    public function authorize(): bool
    {
        $user = $this->viewer();

        return $this->isPoll() ? $user->can('createPoll', Post::class) : $user->can('create', Post::class);
    }

    protected function prepareForValidation(): void
    {
        $poll = $this->input('poll');

        if (is_array($poll) && is_array($poll['options'] ?? null)) {
            $poll['options'] = array_values(array_filter(
                $poll['options'],
                static fn (mixed $option): bool => ! (is_string($option) && trim($option) === '') && $option !== null,
            ));
            $this->merge(['poll' => $poll]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $durations = array_values(array_filter((array) config('xomiton.feed.poll_durations_days', []), static fn (mixed $days): bool => is_int($days)));
        $poll = $this->isPoll();

        return [
            'kind' => ['sometimes', 'string', Rule::in([self::KIND_POST, self::KIND_POLL])],
            'group' => ['sometimes', 'nullable', 'string', 'size:26'],
            'body' => [$poll ? 'nullable' : 'required_without_all:image,images,video', 'nullable', 'string', 'max:'.(int) config('xomiton.feed.post_max_length', Post::BODY_MAX_LENGTH)],
            'tag' => ['sometimes', 'nullable', 'string', Rule::enum(PostTag::class)],
            'visibility' => ['sometimes', 'string', Rule::enum(PostVisibility::class)],
            'is_anonymous' => ['sometimes', 'boolean'],
            'image' => ['nullable', 'file', new UploadAllowed('images')],
            'image_alt' => ['nullable', 'string', 'max:255'],
            'images' => ['sometimes', 'nullable', 'array', 'max:'.FeedMediaLimits::maxImages()],
            'images.*' => ['file', new UploadAllowed('images')],
            'images_alt' => ['sometimes', 'nullable', 'array'],
            'images_alt.*' => ['nullable', 'string', 'max:255'],
            'video' => ['nullable', 'file', new UploadAllowed('video', FeedMediaLimits::videoMaxKb(), strictExtension: true)],
            'video_description' => ['required_with:video', 'nullable', 'string', 'min:'.FeedMediaLimits::descriptionMin(), 'max:'.FeedMediaLimits::descriptionMax()],
            'poll' => [$poll ? 'required' : 'prohibited', 'array'],
            'poll.question' => [$poll ? 'required' : 'nullable', 'string', 'min:'.(int) config('xomiton.feed.poll_question_min', 5), 'max:'.(int) config('xomiton.feed.poll_question_max', 200)],
            'poll.options' => [$poll ? 'required' : 'nullable', 'array', 'min:'.Poll::MIN_OPTIONS, 'max:'.Poll::MAX_OPTIONS],
            'poll.options.*' => ['required', 'string', 'max:'.(int) config('xomiton.feed.poll_option_max_length', 80)],
            'poll.multiple' => ['sometimes', 'boolean'],
            'poll.results_before_vote' => ['sometimes', 'boolean'],
            'poll.duration_days' => ['sometimes', 'nullable', 'integer', Rule::in([0, ...$durations])],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        $min = (int) config('xomiton.feed.poll_question_min', 5);
        $max = (int) config('xomiton.feed.poll_question_max', 200);
        $count = ['min' => Poll::MIN_OPTIONS, 'max' => Poll::MAX_OPTIONS];

        return [
            'body.required_without_all' => (string) __('feed.errors.text_required'),
            'images.max' => (string) __('feed.api.images_max', ['max' => FeedMediaLimits::maxImages()]),
            'video_description.required_with' => (string) __('feed.video.description_required'),
            'video_description.min' => (string) __('feed.video.description_length', ['min' => FeedMediaLimits::descriptionMin(), 'max' => FeedMediaLimits::descriptionMax()]),
            'video_description.max' => (string) __('feed.video.description_length', ['min' => FeedMediaLimits::descriptionMin(), 'max' => FeedMediaLimits::descriptionMax()]),
            'poll.question.min' => (string) __('feed.poll.question_error', ['min' => $min, 'max' => $max]),
            'poll.question.max' => (string) __('feed.poll.question_error', ['min' => $min, 'max' => $max]),
            'poll.options.min' => (string) __('feed.poll.options_count_error', $count),
            'poll.options.max' => (string) __('feed.poll.options_count_error', $count),
        ];
    }

    /**
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [function (Validator $validator): void {
            if (is_string($this->input('group')) && $this->input('group') !== '') {
                $group = $this->group();

                if ($group === null || ! $this->viewer()->can('post', $group)) {
                    $validator->errors()->add('group', (string) __('community.groups.errors.not_member'));
                } elseif ($this->has('visibility') && $this->input('visibility') !== PostVisibility::Public->value) {
                    $validator->errors()->add('visibility', (string) __('feed.api.group_visibility'));
                }
            }

            $this->validateMedia($validator);

            if (! $this->isPoll() && $this->input('tag') === PostTag::Poll->value) {
                $validator->errors()->add('tag', (string) __('feed.api.tag_poll'));
            }

            // Anonyme + abonné·es : qui suit peu de comptes saurait qui a écrit (RG-02, Q72).
            if ($this->boolean('is_anonymous') && $this->input('visibility') === PostVisibility::Followers->value) {
                $validator->errors()->add('visibility', (string) __('feed.api.anonymous_followers'));
            }

            $options = $this->input('poll.options');

            if ($this->isPoll() && is_array($options)) {
                $normalized = array_map(static fn (mixed $label): string => Str::lower(Str::ascii(trim((string) $label))), $options);

                if (count(array_unique($normalized)) !== count($normalized)) {
                    $validator->errors()->add('poll.options', (string) __('feed.poll.options_duplicate'));
                }
            }
        }];
    }

    /**
     * Images (total avec l'ancien champ `image`), alignement des textes alternatifs et exclusions de la vidéo.
     */
    private function validateMedia(Validator $validator): void
    {
        $files = $this->imageFiles();

        if (count($files) + ($this->file('image') instanceof UploadedFile ? 1 : 0) > FeedMediaLimits::maxImages()) {
            $validator->errors()->add('images', (string) __('feed.api.images_max', ['max' => FeedMediaLimits::maxImages()]));
        }

        $alts = $this->input('images_alt');

        if (is_array($alts) && array_diff(array_keys($alts), array_keys($files)) !== []) {
            $validator->errors()->add('images_alt', (string) __('feed.api.images_alt_misaligned'));
        }

        if (! $this->file('video') instanceof UploadedFile) {
            return;
        }

        if ($this->isPoll()) {
            $validator->errors()->add('video', (string) __('feed.video.not_in_poll'));
        } elseif ($files !== [] || $this->file('image') instanceof UploadedFile) {
            $validator->errors()->add('video', (string) __('feed.video.not_with_images'));
        }
    }

    /**
     * Fichiers de `images[]` par clé d'origine (la clé aligne `images_alt`), sans les éléments qui ne sont pas des fichiers.
     *
     * @return array<int|string, UploadedFile>
     */
    private function imageFiles(): array
    {
        $files = $this->file('images');

        if ($files instanceof UploadedFile) {
            return [0 => $files];
        }

        $uploaded = [];

        foreach (is_array($files) ? $files : [] as $key => $file) {
            if ($file instanceof UploadedFile) {
                $uploaded[$key] = $file;
            }
        }

        return $uploaded;
    }

    /**
     * Groupe actif désigné par `group` (ULID), sinon null.
     */
    public function group(): ?Group
    {
        $ulid = $this->input('group');

        if (! is_string($ulid) || $ulid === '') {
            return null;
        }

        return $this->resolvedGroup ??= Group::query()->active()->whereUlid($ulid)->first();
    }

    public function isPoll(): bool
    {
        return $this->input('kind') === self::KIND_POLL;
    }

    public function toDraft(): PostDraft
    {
        $poll = null;

        if ($this->isPoll()) {
            $days = $this->validated('poll.duration_days');
            /** @var list<string> $options */
            $options = array_values(array_map('strval', (array) $this->validated('poll.options')));

            $poll = new PollDraft(
                question: (string) $this->validated('poll.question'),
                options: $options,
                multiple: $this->boolean('poll.multiple'),
                resultsBeforeVote: $this->boolean('poll.results_before_vote'),
                durationDays: is_numeric($days) && (int) $days > 0 ? (int) $days : null,
            );
        }

        $tag = $this->validated('tag');
        $group = $this->group();
        [$images, $imagesAlt] = $this->imagesWithAlt();
        $video = $this->file('video');
        $description = $this->validated('video_description');
        $body = $this->validated('body');

        return new PostDraft(
            body: is_string($body) ? $body : null,
            tag: $poll !== null ? PostTag::Poll : (is_string($tag) && $tag !== '' ? PostTag::from($tag) : PostTag::Testimony),
            visibility: PostVisibility::tryFrom((string) $this->validated('visibility', PostVisibility::Public->value)) ?? PostVisibility::Public,
            anonymous: $this->boolean('is_anonymous'),
            images: $images,
            imagesAlt: $imagesAlt,
            poll: $poll,
            groupId: $group !== null ? (int) $group->getKey() : null,
            video: $video instanceof UploadedFile ? $video : null,
            videoDescription: $video instanceof UploadedFile && is_string($description) ? trim($description) : null,
        );
    }

    /**
     * Images dans l'ordre d'envoi : l'ancien champ `image` d'abord (il compte comme `images[0]`), puis `images[]` ; texte
     * alternatif aligné (`image_alt` pour la première, `images_alt[clé]` pour les suivantes), vide → null.
     *
     * @return array{0: list<UploadedFile>, 1: list<string|null>}
     */
    private function imagesWithAlt(): array
    {
        $images = [];
        $alts = [];
        $legacy = $this->file('image');

        if ($legacy instanceof UploadedFile) {
            $alt = $this->validated('image_alt');
            $images[] = $legacy;
            $alts[] = is_string($alt) && trim($alt) !== '' ? trim($alt) : null;
        }

        $altInput = (array) $this->validated('images_alt', []);

        foreach ($this->imageFiles() as $key => $file) {
            $alt = $altInput[$key] ?? null;
            $images[] = $file;
            $alts[] = is_string($alt) && trim($alt) !== '' ? trim($alt) : null;
        }

        return [$images, $alts];
    }
}
