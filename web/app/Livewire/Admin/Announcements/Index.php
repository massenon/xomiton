<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Announcements;

use App\Application\Platform\Announcements\AnnouncementRules;
use App\Application\Platform\Announcements\Announcements;
use App\Livewire\Concerns\InteractsWithUi;
use App\Livewire\Concerns\ValidatesForm;
use App\Models\Announcement;
use App\Models\User;
use App\Support\LocaleFormatter;
use Illuminate\Contracts\View\View;
use Illuminate\Pagination\LengthAwarePaginator;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Annonce globale `/admin/annonces` (P18-13, Q97) : liste des annonces (programmée, en cours, terminée), modale de création
 * et de modification (titre court, message, lien interne facultatif, niveau, public, période), « Terminer maintenant »,
 * supprimer. La bannière ne s'affiche que dans l'espace membre web et l'application, jamais sur le site public.
 * `announcements.manage` revérifiée à chaque action ; l'annonce ouverte est verrouillée (`#[Locked]`).
 */
final class Index extends Component
{
    use InteractsWithUi;
    use ValidatesForm;
    use WithPagination;

    #[Locked]
    public ?int $announcementId = null;

    /** @var array{title: string, message: string, link_path: string, level: string, audience: string, starts_at: string, ends_at: string} */
    public array $form = ['title' => '', 'message' => '', 'link_path' => '', 'level' => 'info', 'audience' => 'membres', 'starts_at' => '', 'ends_at' => ''];

    public function mount(): void
    {
        $this->authorizeManage();
    }

    public function create(): void
    {
        $this->authorizeManage();
        $this->resetForm();
        $this->form['starts_at'] = now()->setTimezone(self::timezone())->format('Y-m-d\TH:i');
        $this->openModal('xmAnnouncement');
    }

    public function edit(int $id): void
    {
        $this->authorizeManage();
        $announcement = Announcement::query()->findOrFail($id);
        $this->resetErrorBag();
        $this->announcementId = (int) $announcement->getKey();
        $attributes = $announcement->getAttributes();
        $this->form = [
            'title' => (string) $announcement->title,
            'message' => (string) $announcement->message,
            'link_path' => (string) ($attributes['link_path'] ?? ''),
            'level' => $announcement->level()->value,
            'audience' => $announcement->audience()->value,
            'starts_at' => $announcement->asDateTimePublic($attributes['starts_at'])->setTimezone(self::timezone())->format('Y-m-d\TH:i'),
            'ends_at' => ($attributes['ends_at'] ?? null) !== null ? $announcement->asDateTimePublic($attributes['ends_at'])->setTimezone(self::timezone())->format('Y-m-d\TH:i') : '',
        ];
        $this->openModal('xmAnnouncement');
    }

    public function save(Announcements $announcements): void
    {
        $this->authorizeManage();
        $data = $this->validateForm('form', array_map(static fn (string $value): ?string => $value === '' ? null : $value, $this->form), AnnouncementRules::rules(), [
            'title' => (string) __('admin_announcements.fields.title'),
            'message' => (string) __('admin_announcements.fields.message'),
            'link_path' => (string) __('admin_announcements.fields.link_path'),
            'level' => (string) __('admin_announcements.fields.level'),
            'audience' => (string) __('admin_announcements.fields.audience'),
            'starts_at' => (string) __('admin_announcements.fields.starts_at'),
            'ends_at' => (string) __('admin_announcements.fields.ends_at'),
        ]);

        /** @var array{title: string, message: string, link_path?: string|null, level: string, audience: string, starts_at: string, ends_at?: string|null} $data */
        $announcements->save($this->announcementId === null ? null : Announcement::query()->findOrFail($this->announcementId), $data, $this->actor());
        $this->toast((string) __($this->announcementId === null ? 'admin_announcements.toasts.created' : 'admin_announcements.toasts.saved'));
        $this->closeModal('xmAnnouncement');
        $this->resetForm();
    }

    public function end(int $id, Announcements $announcements): void
    {
        $this->authorizeManage();
        $announcements->end(Announcement::query()->findOrFail($id), $this->actor());
        $this->toast((string) __('admin_announcements.toasts.ended'), 'warning');
    }

    public function delete(int $id, Announcements $announcements): void
    {
        $this->authorizeManage();
        $announcements->delete(Announcement::query()->findOrFail($id), $this->actor());
        $this->toast((string) __('admin_announcements.toasts.deleted'), 'warning');
    }

    public function resetForm(): void
    {
        $this->announcementId = null;
        $this->form = ['title' => '', 'message' => '', 'link_path' => '', 'level' => 'info', 'audience' => 'membres', 'starts_at' => '', 'ends_at' => ''];
        $this->resetErrorBag();
    }

    public function render(): View
    {
        /** @var LengthAwarePaginator<int, Announcement> $rows */
        $rows = Announcement::query()->orderByDesc('starts_at')->orderByDesc('id')->paginate(15);

        return view('livewire.admin.announcements.index', [
            'rows' => $rows,
            'format' => LocaleFormatter::make(null, self::timezone()),
        ])->layout('components.layouts.admin', [
            'title' => __('admin_announcements.title'),
            'adminPage' => 'annonces',
        ]);
    }

    private static function timezone(): string
    {
        return (string) config('xomiton.display_timezone', 'UTC');
    }

    private function authorizeManage(): void
    {
        abort_unless($this->actor()->can('announcements.manage'), 403);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
