<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Network;

use App\Application\Admin\Network\ModerateNetwork;
use App\Enums\ContentStatus;
use App\Enums\PostStatus;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\WithDataTable;
use App\Models\Event;
use App\Models\ForumTopic;
use App\Models\Group;
use App\Models\Page;
use App\Models\Post;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Attributes\Computed;
use Livewire\Component;

/**
 * Réseau social `/admin/reseau` (P12-05, FR-SOC-06 ; prototype admin/reseau.php) : onglets Groupes · Pages · Événements ·
 * Forums (sujets) · Publications, KPI, tableaux (recherche sur les colonnes en clair, tri, filtre d'état, pagination
 * serveur) et actions voir, masquer / rendre visible, supprimer, approuver et mettre en avant (badges « Approuvée » / « En
 * avant »). Une page d'organisation n'est visible du public qu'une fois approuvée. Écran ouvert à `posts.moderate` (modérateur·rice,
 * admin, superadmin) ; masquer, rendre visible et supprimer rejouent la policy du contenu, approuver et mettre en avant
 * exigent `moderation.manage` (PagePolicy). Aucun récit ni identité d'une publication anonyme n'est affiché (RG-02).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithDataTable;

    public const TABS = ['groupes', 'pages', 'evenements', 'forums', 'publications'];

    /** Filtres d'état : '' (tous), visible, hidden, pending (pages non approuvées). */
    public const STATES = ['visible', 'hidden', 'pending'];

    #[SafeUrl(as: 'onglet', except: 'groupes')]
    public string $tab = 'groupes';

    #[SafeUrl(as: 'etat', except: '')]
    public string $state = '';

    public function mount(): void
    {
        abort_unless($this->actor()->can('posts.moderate'), 403);

        if (! in_array($this->tab, self::TABS, true)) {
            $this->tab = 'groupes';
        }
    }

    public function setTab(string $tab): void
    {
        if (! in_array($tab, self::TABS, true)) {
            return;
        }

        $this->tab = $tab;
        $this->reset('search', 'sort', 'direction', 'state');
        $this->clearSelection();
        $this->resetPage();
    }

    // --- WithDataTable -----------------------------------------------------------------------------------------------

    protected function sortableColumns(): array
    {
        return match ($this->tab) {
            'pages' => ['name' => 'name', 'followers' => 'followers_count', 'created' => 'created_at'],
            'evenements' => ['title' => 'title', 'date' => 'starts_at', 'going' => 'going_count'],
            'forums' => ['title' => 'title', 'replies' => 'replies_count', 'created' => 'created_at'],
            'publications' => ['created' => 'created_at', 'reports' => 'reports_count', 'likes' => 'likes_count'],
            default => ['name' => 'name', 'members' => 'members_count', 'created' => 'created_at'],
        };
    }

    protected function searchableColumns(): array
    {
        return match ($this->tab) {
            'pages' => ['name', 'category', 'description'],
            'evenements' => ['title', 'place', 'organizer_label'],
            'forums' => ['title'],
            'publications' => ['body', 'tag'],
            default => ['name', 'description', 'slug'],
        };
    }

    protected function defaultSort(): array
    {
        return match ($this->tab) {
            'evenements' => ['date', 'desc'],
            'publications', 'forums' => ['created', 'desc'],
            default => ['created', 'desc'],
        };
    }

    protected function filterProperties(): array
    {
        return ['state'];
    }

    // --- Données -----------------------------------------------------------------------------------------------------

    /**
     * @return list<array{label: string, value: string, icon: string, variant: string, sub?: string}>
     */
    #[Computed]
    public function kpis(): array
    {
        $format = $this->formatter();
        $pending = Page::query()->where('is_approved', false)->count();

        return [
            ['label' => __('admin_network.kpi.groups'), 'value' => $format->number(Group::query()->count()), 'icon' => 'people', 'variant' => ''],
            ['label' => __('admin_network.kpi.pages'), 'value' => $format->number(Page::query()->count()), 'icon' => 'flag', 'variant' => 'violet', 'sub' => $pending > 0 ? trans_choice('admin_network.kpi.pending', $pending, ['count' => $pending]) : null],
            ['label' => __('admin_network.kpi.events'), 'value' => $format->number(Event::query()->published()->count()), 'icon' => 'calendar-event', 'variant' => 'success'],
            ['label' => __('admin_network.kpi.posts'), 'value' => $format->number(Post::query()->where('status', PostStatus::Published)->count()), 'icon' => 'card-text', 'variant' => 'warning'],
        ];
    }

    /** @return array<string, int> */
    #[Computed]
    public function counts(): array
    {
        return [
            'groupes' => Group::query()->count(),
            'pages' => Page::query()->where('is_approved', false)->count(),
            'evenements' => Event::query()->count(),
            'forums' => ForumTopic::query()->count(),
            'publications' => Post::query()->count(),
        ];
    }

    public function render(): View
    {
        $rows = $this->paginateQuery(match ($this->tab) {
            'pages' => $this->pageQuery(),
            'evenements' => $this->eventQuery(),
            'forums' => $this->topicQuery(),
            'publications' => $this->postQuery(),
            default => $this->groupQuery(),
        });
        [$sortKey, $sortDirection] = $this->currentSort();

        return view('livewire.admin.network.index', [
            'rows' => $rows,
            'sortKey' => $sortKey,
            'sortDirection' => $sortDirection,
            'format' => $this->formatter(),
            'canCurate' => $this->actor()->can('moderation.manage'),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_network.title'),
            'adminPage' => 'reseau',
        ]);
    }

    /** @return Builder<Group> */
    private function groupQuery(): Builder
    {
        return Group::query()->with(['zone', 'owner:id,pseudo'])->when($this->state === 'hidden', fn (Builder $q) => $q->where('is_active', false))
            ->when($this->state === 'visible', fn (Builder $q) => $q->where('is_active', true));
    }

    /** @return Builder<Page> */
    private function pageQuery(): Builder
    {
        return Page::query()->with('owner:id,pseudo')
            ->when($this->state === 'pending', fn (Builder $q) => $q->where('is_approved', false))
            ->when($this->state === 'hidden', fn (Builder $q) => $q->where('is_active', false))
            ->when($this->state === 'visible', fn (Builder $q) => $q->where('is_approved', true)->where('is_active', true));
    }

    /** @return Builder<Event> */
    private function eventQuery(): Builder
    {
        return Event::query()
            ->when($this->state === 'hidden', fn (Builder $q) => $q->where('status', ContentStatus::Hidden))
            ->when($this->state === 'visible', fn (Builder $q) => $q->where('status', ContentStatus::Published));
    }

    /** @return Builder<ForumTopic> */
    private function topicQuery(): Builder
    {
        return ForumTopic::query()->with(['forum:id,name', 'user:id,pseudo'])
            ->when($this->state === 'hidden', fn (Builder $q) => $q->where('status', PostStatus::Hidden))
            ->when($this->state === 'visible', fn (Builder $q) => $q->where('status', PostStatus::Published));
    }

    /** @return Builder<Post> */
    private function postQuery(): Builder
    {
        return Post::query()->with(['user:id,pseudo', 'group:id,name', 'page:id,name'])
            ->when($this->state === 'hidden', fn (Builder $q) => $q->where('status', PostStatus::Hidden))
            ->when($this->state === 'visible', fn (Builder $q) => $q->where('status', PostStatus::Published));
    }

    // --- Actions -----------------------------------------------------------------------------------------------------

    public function hide(string $type, int $id, ModerateNetwork $network): void
    {
        $subject = $this->subject($type, $id);
        $this->authorizeCuration($subject, 'moderate');

        if ($network->hide($subject, $this->actor())) {
            $this->toast((string) __('admin_network.toasts.hidden'), 'warning');
        }

        unset($this->kpis);
    }

    public function show(string $type, int $id, ModerateNetwork $network): void
    {
        $subject = $this->subject($type, $id);
        $this->authorizeCuration($subject, 'moderate');

        if ($network->show($subject, $this->actor())) {
            $this->toast((string) __('admin_network.toasts.shown'));
        }

        unset($this->kpis);
    }

    public function remove(string $type, int $id, ModerateNetwork $network): void
    {
        $subject = $this->subject($type, $id);
        $this->authorize('delete', $subject);
        $network->delete($subject, $this->actor());
        unset($this->kpis, $this->counts);
        $this->toast((string) __('admin_network.toasts.deleted'), 'warning');
    }

    public function approve(int $id, bool $approved, ModerateNetwork $network): void
    {
        $page = Page::query()->findOrFail($id);
        $this->authorize('approve', $page);
        $network->approve($page, $approved, $this->actor());
        unset($this->kpis, $this->counts);
        $this->toast((string) __($approved ? 'admin_network.toasts.approved' : 'admin_network.toasts.unapproved', ['name' => $page->name]), $approved ? 'success' : 'warning');
    }

    public function feature(string $type, int $id, ModerateNetwork $network): void
    {
        $subject = $this->subject($type, $id);

        if (! $subject instanceof Page && ! $subject instanceof Group) {
            return;
        }

        $subject instanceof Page ? $this->authorize('feature', $subject) : abort_unless($this->actor()->can('moderation.manage'), 403);
        $featured = ! $subject->is_featured;
        $network->feature($subject, $featured, $this->actor());
        $this->toast((string) __($featured ? 'admin_network.toasts.featured' : 'admin_network.toasts.unfeatured'));
    }

    private function subject(string $type, int $id): Model
    {
        return match ($type) {
            'group' => Group::query()->findOrFail($id),
            'page' => Page::query()->findOrFail($id),
            'event' => Event::query()->findOrFail($id),
            'topic' => ForumTopic::query()->findOrFail($id),
            'post' => Post::query()->findOrFail($id),
            default => abort(404),
        };
    }

    /** Publication et sujet : policy `moderate` ; groupe, page, événement : `moderation.manage`. */
    private function authorizeCuration(Model $subject, string $ability): void
    {
        if ($subject instanceof Post || $subject instanceof ForumTopic) {
            $this->authorize($ability, $subject);

            return;
        }

        abort_unless($this->actor()->can('moderation.manage'), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }

    private function formatter(): LocaleFormatter
    {
        return LocaleFormatter::make(null, (string) config('xomiton.display_timezone', 'UTC'));
    }
}
