<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Network;

use App\Application\Admin\Network\ManageForums;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Models\Forum;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Onglet « Gestion des forums » de `/admin/reseau` (P18-10) : liste ordonnée des forums (nom, description, icône, sujets,
 * état), créer / modifier dans une modale, monter / descendre (annonce aria-live, focus conservé), archiver / réactiver.
 * `forums.manage` (admin, superadmin) est revérifiée au montage et à chaque action ; l'identifiant du forum ouvert est
 * verrouillé (`#[Locked]`). Les écritures passent par ManageForums (journal `forums.*`).
 */
final class Forums extends Component
{
    use InteractsWithUi;
    use ValidatesForm;

    #[Locked]
    public ?int $forumId = null;

    /** @var array{name: string, description: string, icon: string} */
    public array $form = ['name' => '', 'description' => '', 'icon' => 'chat-square-text'];

    public string $announce = '';

    public function mount(): void
    {
        $this->authorizeManage();
    }

    /** @return Collection<int, Forum> */
    #[Computed]
    public function forums(): Collection
    {
        return Forum::query()->ordered()->get();
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->openModal('xmForumForm');
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $forum = Forum::query()->findOrFail($id);
        $this->resetErrorBag();
        $this->forumId = (int) $forum->getKey();
        $icon = (string) preg_replace('/^bi-/', '', (string) $forum->icon);
        $this->form = [
            'name' => (string) $forum->name,
            'description' => (string) $forum->description,
            'icon' => in_array($icon, ManageForums::ICONS, true) ? $icon : 'chat-square-text',
        ];
        $this->openModal('xmForumForm');
    }

    public function save(ManageForums $manage): void
    {
        $this->authorizeManage();
        /** @var array{name: string, description: string, icon: string} $data */
        $data = $this->validateForm('form', $this->form, ManageForums::rules(), [
            'name' => (string) __('admin_network.manage.fields.name'),
            'description' => (string) __('admin_network.manage.fields.description'),
            'icon' => (string) __('admin_network.manage.fields.icon'),
        ]);

        if ($this->forumId === null) {
            $forum = $manage->create($data, $this->actor());
            $this->toast((string) __('admin_network.manage.toasts.created', ['name' => $forum->name]));
        } else {
            $manage->update(Forum::query()->findOrFail($this->forumId), $data, $this->actor());
            $this->toast((string) __('admin_network.manage.toasts.updated'));
        }

        $this->closeModal('xmForumForm');
        $this->resetForm();
        unset($this->forums);
    }

    public function move(int $id, int $direction, ManageForums $manage): void
    {
        $this->authorizeManage();
        $forum = Forum::query()->findOrFail($id);
        $direction = $direction < 0 ? -1 : 1;

        try {
            $target = $manage->move($forum, $direction, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        unset($this->forums);
        $total = $this->forums()->count();
        $this->announce = (string) __('admin_network.manage.moved', ['name' => $forum->name, 'position' => $target + 1, 'total' => $total]);
        // Au bord de la liste, le focus passe sur l'autre bouton (le premier « Monter » est inactif).
        $button = $direction < 0 ? ($target === 0 ? 'Down' : 'Up') : ($target === $total - 1 ? 'Up' : 'Down');
        $this->dispatch('xm-forum-focus', id: 'xmForum'.$button.$forum->getKey());
    }

    public function archive(int $id, bool $archived, ManageForums $manage): void
    {
        $this->authorizeManage();
        $forum = Forum::query()->findOrFail($id);

        if ($manage->archive($forum, $archived, $this->actor())) {
            $this->toast((string) __($archived ? 'admin_network.manage.toasts.archived' : 'admin_network.manage.toasts.restored', ['name' => $forum->name]), $archived ? 'warning' : 'success');
        }

        unset($this->forums);
    }

    public function resetForm(): void
    {
        $this->forumId = null;
        $this->form = ['name' => '', 'description' => '', 'icon' => 'chat-square-text'];
        $this->resetErrorBag();
    }

    public function render(): View
    {
        return view('livewire.admin.network.forums');
    }

    private function authorizeManage(): void
    {
        abort_unless($this->actor()->can('forums.manage'), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
