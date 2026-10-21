<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Ccc;

use App\Application\Admin\Content\ManageCccTools;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\CccKind;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\CccTool;
use App\Models\Law;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Outils CCC `/admin/ccc` (P12-04 ; prototype admin/ccc.php) : onglets Manuel · Boîtes à images · BD SSR · Lois, KPI,
 * publié / masqué (un outil masqué disparaît des écrans membre et pair, un texte masqué de `/lois`), remplacement de
 * l'image (MediaUploader) et texte alternatif de chaque planche d'une bande dessinée. Permission `ccc_tools.manage`
 * (admin, superadmin) ; l'onglet Lois exige aussi `laws.manage`. Chaque écriture est journalisée par ManageCccTools.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithFileUploads;

    public const TABS = ['manuel', 'boite_images', 'bd', 'lois'];

    #[SafeUrl(as: 'onglet', except: 'manuel')]
    public string $tab = 'manuel';

    /** Outil dont la modale « Image » ou « Planches » est ouverte (jamais modifiable depuis le navigateur). */
    #[Locked]
    public ?int $toolId = null;

    public mixed $image = null;

    /** @var list<string> */
    public array $alts = [];

    public function mount(): void
    {
        abort_unless($this->actor()->can('ccc_tools.manage'), 403);

        if (! in_array($this->tab, $this->tabs(), true)) {
            $this->tab = 'manuel';
        }
    }

    /** @return list<string> */
    public function tabs(): array
    {
        return $this->actor()->can('laws.manage') ? self::TABS : array_values(array_diff(self::TABS, ['lois']));
    }

    public function setTab(string $tab): void
    {
        if (in_array($tab, $this->tabs(), true)) {
            $this->tab = $tab;
        }
    }

    public function toggle(int $id, ManageCccTools $manage): void
    {
        $tool = $this->tool($id);
        $publish = ! $tool->is_published;
        $manage->setPublished($tool, $publish, $this->actor());
        unset($this->tools, $this->kpis);
        $this->toast((string) __($publish ? 'admin_ccc.toasts.published' : 'admin_ccc.toasts.unpublished', ['title' => $tool->title]), $publish ? 'success' : 'warning');
    }

    public function toggleLaw(int $id, ManageCccTools $manage): void
    {
        abort_unless($this->actor()->can('laws.manage'), 403);
        $law = Law::query()->findOrFail($id);
        $publish = ! $law->is_published;
        $manage->setLawPublished($law, $publish, $this->actor());
        unset($this->laws, $this->kpis);
        $this->toast((string) __($publish ? 'admin_ccc.toasts.published' : 'admin_ccc.toasts.unpublished', ['title' => $law->title]), $publish ? 'success' : 'warning');
    }

    public function openImage(int $id): void
    {
        $tool = $this->tool($id);
        $this->resetModal();
        $this->toolId = (int) $tool->getKey();
        $this->openModal('xmCccImage');
    }

    public function openAlts(int $id): void
    {
        $tool = $this->tool($id);
        $this->resetModal();
        $this->toolId = (int) $tool->getKey();
        $this->alts = array_values(array_map(static fn (array $page): string => (string) ($page['alt'] ?? ''), array_filter((array) $tool->pages, 'is_array')));
        $this->openModal('xmCccAlts');
    }

    public function resetModal(): void
    {
        $this->reset('toolId', 'image', 'alts');
        $this->resetErrorBag();
        unset($this->openedTool);
    }

    public function saveImage(ManageCccTools $manage): void
    {
        $tool = $this->openedTool ?? abort(404);
        $this->validate(['image' => ['required', 'file', 'max:5120']], [], ['image' => (string) __('admin_ccc.image.label')]);

        try {
            $manage->replaceImage($tool, $this->image, $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('image', $refused->userMessage());

            return;
        }

        $this->closeModal('xmCccImage');
        $this->toast((string) __('admin_ccc.toasts.image', ['title' => $tool->title]));
        $this->resetModal();
        unset($this->tools, $this->kpis);
    }

    public function saveAlts(ManageCccTools $manage): void
    {
        $tool = $this->openedTool ?? abort(404);

        try {
            $manage->saveAlts($tool, $this->alts, $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('alts', $refused->userMessage());

            return;
        }

        $this->closeModal('xmCccAlts');
        $this->toast((string) __('admin_ccc.toasts.alts', ['title' => $tool->title]));
        $this->resetModal();
        unset($this->tools);
    }

    #[Computed]
    public function openedTool(): ?CccTool
    {
        return $this->toolId === null ? null : CccTool::query()->find($this->toolId);
    }

    /** @return Collection<int, CccTool> */
    #[Computed]
    public function tools(): Collection
    {
        $kind = CccKind::tryFrom($this->tab);

        return $kind === null ? new Collection : CccTool::query()->ofKind($kind)->ordered()->get();
    }

    /** @return Collection<int, Law> */
    #[Computed]
    public function laws(): Collection
    {
        return $this->tab === 'lois' ? Law::query()->ordered()->get() : new Collection;
    }

    /**
     * @return array<string, int>
     */
    #[Computed]
    public function counts(): array
    {
        $byKind = CccTool::query()->selectRaw('kind, count(*) as total')->groupBy('kind')->pluck('total', 'kind');

        return [
            'manuel' => (int) ($byKind[CccKind::Manual->value] ?? 0),
            'boite_images' => (int) ($byKind[CccKind::ImageBox->value] ?? 0),
            'bd' => (int) ($byKind[CccKind::Comic->value] ?? 0),
            'lois' => Law::query()->count(),
        ];
    }

    /**
     * @return array{total: int, published: int, hidden: int, images: int}
     */
    #[Computed]
    public function kpis(): array
    {
        $total = CccTool::query()->count() + Law::query()->count();
        $published = CccTool::query()->published()->count() + Law::query()->published()->count();

        return [
            'total' => $total,
            'published' => $published,
            'hidden' => $total - $published,
            // Image remplacée par l'équipe : fichier téléversé (hors des images du dépôt, `assets/…`).
            'images' => CccTool::query()->whereNotNull('image_path')->where('image_path', 'not like', 'assets/%')->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.admin.ccc.index', ['tabs' => $this->tabs()])->layout('components.layouts.admin', [
            'title' => __('admin_ccc.title'),
            'adminPage' => 'ccc',
        ]);
    }

    private function tool(int $id): CccTool
    {
        abort_unless($this->actor()->can('ccc_tools.manage'), 403);

        return CccTool::query()->findOrFail($id);
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
