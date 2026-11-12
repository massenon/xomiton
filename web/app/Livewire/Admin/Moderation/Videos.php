<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Moderation;

use App\Application\Feed\PostLinks;
use App\Application\Feed\ReviewVideo;
use App\Domain\Moderation\Exceptions\ModerationRefused;
use App\Enums\PostStatus;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\Post;
use App\Models\PostMedia;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Vidéos à relire `/admin/moderation/videos` (Q96) : file des vidéos envoyées par les membres et **invisibles tant
 * qu'elles ne sont pas approuvées**, les plus anciennes d'abord. Chaque ligne : lecteur HTML5 natif (adresse signée courte,
 * plages `Range`), description fournie par la personne, auteur·rice (même pour une publication anonyme : la modération le
 * voit), date d'envoi, poids. Actions : Approuver (statut publié, cloche « Ta vidéo a été publiée ») ou Refuser (motif de la
 * liste obligatoire + texte libre ≤ 300 car., cloche à l'auteur·rice, fichier supprimé après le délai de conservation).
 * Permission `moderation.manage` à l'ouverture ET à chaque action (PostPolicy `review` : jamais sa propre vidéo, vidéo encore en
 * attente) ; l'identifiant de la vidéo décidée est `#[Locked]`. Journal d'audit sans contenu (ReviewVideo).
 * La vidéo n'est pas retouchée à l'envoi : ses métadonnées (lieu) peuvent s'y trouver, d'où le motif « vie privée ».
 */
final class Videos extends Component
{
    use InteractsWithUi;
    use WithPagination;

    public const PER_PAGE = 10;

    /** Vidéo de la modale de décision (jamais modifiable depuis le navigateur). */
    #[Locked]
    public ?int $postId = null;

    /** `approve` | `reject` */
    #[Locked]
    public string $decision = '';

    public string $reasonCode = '';

    public string $note = '';

    public function mount(): void
    {
        Gate::authorize('moderation.manage');
    }

    /** @return array{pending: int, rejected: int} */
    #[Computed]
    public function stats(): array
    {
        return [
            'pending' => Post::query()->where('status', PostStatus::Pending)->count(),
            'rejected' => Post::query()->where('status', PostStatus::Rejected)->count(),
        ];
    }

    public function render(): View
    {
        Gate::authorize('moderation.manage');

        $actor = $this->actor();
        $links = app(PostLinks::class);
        /** @var LengthAwarePaginator<int, Post> $posts */
        $posts = Post::query()->awaitingReview()->with(['user:id,pseudo', 'media'])->paginate(self::PER_PAGE);

        $rows = $posts->getCollection()->map(static function (Post $post) use ($links, $actor): array {
            $media = $post->media->first(static fn (PostMedia $item): bool => $item->isVideo());

            return [
                'post' => $post,
                'description' => $media?->alt,
                'size' => $media?->size,
                'mime' => $media?->mime,
                'url' => $media !== null ? $links->video($post, $media, $actor) : null,
                'own' => $post->user_id === $actor->getKey(),
            ];
        })->all();

        return view('livewire.admin.moderation.videos', [
            'rows' => $rows,
            'paginator' => $posts,
            'format' => LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC')),
            'reasons' => ReviewVideo::reasons(),
            'noteMax' => ReviewVideo::noteMax(),
        ])->layout('components.layouts.admin', [
            'title' => __('admin.moderation.video.title'),
            'adminPage' => 'moderation',
        ]);
    }

    // --- Actions -----------------------------------------------------------------------------------------------------

    public function openDecision(int $id, string $decision): void
    {
        $post = Post::query()->findOrFail($id);
        $this->authorize('review', $post);

        if (! in_array($decision, ['approve', 'reject'], true)) {
            return;
        }

        $this->postId = (int) $post->getKey();
        $this->decision = $decision;
        $this->reasonCode = '';
        $this->note = '';
        $this->resetErrorBag();
        $this->openModal('xmVideoDecide');
    }

    public function closeDecision(): void
    {
        $this->reset('postId', 'decision', 'reasonCode', 'note');
        $this->resetErrorBag();
    }

    public function approve(ReviewVideo $review): void
    {
        if (! $this->attempt(fn () => $review->approve($this->currentPost(), $this->actor()))) {
            return;
        }

        $this->finish('approved');
    }

    public function reject(ReviewVideo $review): void
    {
        $max = ReviewVideo::noteMax();

        $this->validate([
            'reasonCode' => ['required', 'string', 'in:'.implode(',', ReviewVideo::reasons())],
            'note' => [$this->reasonCode === 'other' ? 'required' : 'nullable', 'string', 'max:'.$max],
        ], [], [
            'reasonCode' => (string) __('admin.moderation.video.reason'),
            'note' => (string) __('admin.moderation.video.note'),
        ]);

        if (! $this->attempt(fn () => $review->reject($this->currentPost(), $this->actor(), $this->reasonCode, $this->note))) {
            return;
        }

        $this->finish('rejected');
    }

    // --- Intérieur ---------------------------------------------------------------------------------------------------

    private function finish(string $outcome): void
    {
        $this->closeModal('xmVideoDecide');
        $this->closeDecision();
        unset($this->stats);
        $this->resetPage();
        $this->toast((string) __('admin.moderation.video.toasts.'.$outcome), $outcome === 'approved' ? 'success' : 'info');
    }

    /**
     * @throws ModerationRefused vidéo déjà décidée ou retirée depuis l'ouverture de la modale (toast, pas de 403)
     */
    private function currentPost(): Post
    {
        abort_if($this->postId === null, 404);

        $post = Post::query()->find($this->postId);

        if ($post === null || ! $post->isPendingReview()) {
            throw new ModerationRefused('video_not_pending');
        }

        return $post;
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function attempt(callable $action): bool
    {
        try {
            $action();
        } catch (ModerationRefused $refused) {
            $this->toast($refused->userMessage(), 'error');
            $this->closeModal('xmVideoDecide');
            $this->closeDecision();
            unset($this->stats);

            return false;
        }

        return true;
    }
}
