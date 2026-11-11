<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Ccc;

use App\Application\Admin\Content\ManageCccTools;
use App\Application\Admin\Content\ManageLaws;
use App\Domain\Content\Exceptions\ContentRefused;
use App\Enums\CccKind;
use App\Livewire\Attributes\SafeUrl;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\CccTool;
use App\Models\Law;
use App\Models\User;
use Illuminate\Contracts\View\View;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Livewire\WithFileUploads;

/**
 * Outils CCC `/admin/ccc` (P12-04 ; prototype admin/ccc.php) : onglets Manuel · Boîtes à images · BD SSR · Lois, KPI,
 * publié / masqué (un outil masqué disparaît des écrans membre et pair, un texte masqué de `/lois`), remplacement de
 * l'image (MediaUploader). Onglets Boîtes à images et BD SSR (même mécanique, le kind est celui de l'onglet à la création,
 * celui de l'outil ensuite) : création, titre, planches (ajout, retrait, légende, texte alternatif, ordre au clavier) et
 * suppression. Onglet Lois : création, modification de tous les champs, ordre (monter / descendre), publié / masqué et
 * suppression (ManageLaws). Permission `ccc_tools.manage` (admin, superadmin) vérifiée à chaque action (jamais seulement à
 * l'affichage) ; l'onglet Lois exige aussi `laws.manage`. Chaque écriture est journalisée par ManageCccTools / ManageLaws.
 */
final class Index extends Component
{
    use InteractsWithUi;
    use WithFileUploads;

    public const TABS = ['manuel', 'boite_images', 'bd', 'lois'];

    #[SafeUrl(as: 'onglet', except: 'manuel')]
    public string $tab = 'manuel';

    /** Outil dont la modale (image, titre, planches, suppression) est ouverte ; null = création (jamais modifiable depuis le navigateur). */
    #[Locked]
    public ?int $toolId = null;

    /** Rang (0 pour la première) de la planche dont le retrait attend sa confirmation (jamais modifiable depuis le navigateur). */
    #[Locked]
    public ?int $removeIndex = null;

    public const DRAFT_PAGES_MAX = 12;

    public mixed $image = null;

    /** Formulaire « Nouvelle BD » / « Nouvelle boîte à images » / « Modifier le titre ». */
    public string $title = '';

    public bool $published = false;

    public mixed $cover = null;

    /** @var array<int, mixed> lignes `{file, caption, alt}` : saisie du navigateur, relue ligne à ligne */
    public array $draftPages = [];

    /** Formulaire « Ajouter une planche ». */
    public mixed $newImage = null;

    public string $newCaption = '';

    public string $newAlt = '';

    /**
     * Légende et texte alternatif des planches de l'outil ouvert, dans l'ordre des planches (lignes `{caption, alt}`).
     *
     * @var array<int, mixed>
     */
    public array $pageEdits = [];

    /** Texte de loi dont une modale (modification, suppression) est ouverte ; null = création (jamais modifiable depuis le navigateur). */
    #[Locked]
    public ?int $lawId = null;

    /** Formulaire « Nouveau texte » / « Modifier le texte ». */
    public string $lawTitle = '';

    public string $lawSubtitle = '';

    public string $lawCategory = '';

    public string $lawSummary = '';

    public string $lawSimple = '';

    public string $lawUrl = '';

    public bool $lawPublished = false;

    /** Phrase lue par les lecteurs d'écran après un déplacement. */
    public string $announce = '';

    /** Change à chaque ouverture : un champ fichier est recréé (le navigateur ne se vide pas tout seul). */
    public int $fileInputKey = 0;

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

        try {
            if (ManageCccTools::isPaged($tool->kind)) {
                $manage->setPagedPublished($tool, $publish, $this->actor());
            } else {
                $manage->setPublished($tool, $publish, $this->actor());
            }
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');
            unset($this->tools, $this->kpis);

            return;
        }

        unset($this->tools, $this->kpis);
        $this->toast((string) __($publish ? 'admin_ccc.toasts.published' : 'admin_ccc.toasts.unpublished', ['title' => $tool->title]), $publish ? 'success' : 'warning');
    }

    public function toggleLaw(int $id, ManageLaws $manage): void
    {
        $law = $this->law($id);
        $publish = ! $law->is_published;
        $manage->setPublished($law, $publish, $this->actor());
        unset($this->laws, $this->kpis);
        $this->toast((string) __($publish ? 'admin_ccc.toasts.published' : 'admin_ccc.toasts.unpublished', ['title' => $law->title]), $publish ? 'success' : 'warning');
    }

    public function openLawCreate(): void
    {
        $this->authorizeLaws();
        $this->resetModal();
        $this->openModal('xmCccLaw');
    }

    public function openLawEdit(int $id): void
    {
        $law = $this->law($id);
        $this->resetModal();
        $this->lawId = (int) $law->getKey();
        $this->lawTitle = $law->title;
        $this->lawSubtitle = (string) $law->subtitle;
        $this->lawCategory = $law->category;
        $this->lawSummary = $law->summary;
        $this->lawSimple = (string) $law->simple_summary;
        $this->lawUrl = (string) $law->official_url;
        $this->lawPublished = $law->is_published;
        $this->openModal('xmCccLaw');
    }

    public function openLawDelete(int $id): void
    {
        $law = $this->law($id);
        $this->resetModal();
        $this->lawId = (int) $law->getKey();
        $this->openModal('xmCccLawDelete');
    }

    /** Crée le texte (`lawId` nul) ou enregistre ses modifications. */
    public function saveLaw(ManageLaws $manage): void
    {
        $this->authorizeLaws();
        $law = $this->lawId === null ? null : ($this->openedLaw() ?? abort(404));
        $text = ['string', 'not_regex:/<\/?[a-z!][^>]*>/i'];
        $this->validate([
            'lawTitle' => ['required', ...$text, 'max:'.ManageLaws::TITLE_MAX],
            'lawSubtitle' => ['nullable', ...$text, 'max:'.ManageLaws::SUBTITLE_MAX],
            'lawCategory' => ['required', ...$text, 'max:'.ManageLaws::CATEGORY_MAX],
            'lawSummary' => ['required', ...$text, 'max:'.ManageLaws::SUMMARY_MAX],
            'lawSimple' => ['nullable', ...$text, 'max:'.ManageLaws::SUMMARY_MAX],
            'lawUrl' => ['nullable', 'string', 'url:http,https', 'max:'.ManageLaws::URL_MAX],
            'lawPublished' => ['boolean'],
        ], [
            'not_regex' => (string) __('admin_ccc.law.no_markup'),
        ], [
            'lawTitle' => (string) __('admin_ccc.law.fields.title'),
            'lawSubtitle' => (string) __('admin_ccc.law.fields.subtitle'),
            'lawCategory' => (string) __('admin_ccc.law.fields.category'),
            'lawSummary' => (string) __('admin_ccc.law.fields.summary'),
            'lawSimple' => (string) __('admin_ccc.law.fields.simple_summary'),
            'lawUrl' => (string) __('admin_ccc.law.fields.official_url'),
        ]);

        try {
            $saved = $manage->save($law, [
                'title' => $this->lawTitle,
                'subtitle' => $this->lawSubtitle,
                'category' => $this->lawCategory,
                'summary' => $this->lawSummary,
                'simple_summary' => $this->lawSimple,
                'official_url' => $this->lawUrl,
                'is_published' => $this->lawPublished,
            ], $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('law', $refused->userMessage());

            return;
        }

        $this->closeModal('xmCccLaw');
        $this->toast((string) __($law === null ? 'admin_ccc.toasts.law_created' : 'admin_ccc.toasts.law_updated', ['title' => $saved->title]));
        $this->resetModal();
        unset($this->laws, $this->kpis, $this->counts, $this->lawCategories);
    }

    /** Monte (−1) ou descend (+1) un texte ; le focus suit son texte. */
    public function moveLaw(int $id, int $direction, ManageLaws $manage): void
    {
        $law = $this->law($id);
        $direction = $direction < 0 ? -1 : 1;

        try {
            $target = $manage->move($law, $direction, $this->actor());
        } catch (ContentRefused $refused) {
            $this->toast($refused->userMessage(), 'error');

            return;
        }

        unset($this->laws);
        $total = count($this->laws());
        $this->announce = (string) __('admin_ccc.law.moved', ['title' => $law->title, 'position' => $target + 1, 'total' => $total]);

        // Au bord de la liste, le focus passe sur l'autre bouton (le premier « Monter » est inactif).
        $button = $direction < 0 ? ($target === 0 ? 'Down' : 'Up') : ($target === $total - 1 ? 'Up' : 'Down');
        $this->dispatch('xm-ccc-focus', id: 'xmLaw'.$button.$law->getKey());
    }

    public function deleteLaw(ManageLaws $manage): void
    {
        $this->authorizeLaws();
        $law = $this->openedLaw() ?? abort(404);
        $manage->delete($law, $this->actor());
        $this->closeModal('xmCccLawDelete');
        $this->toast((string) __('admin_ccc.toasts.law_deleted', ['title' => $law->title]));
        $this->resetModal();
        unset($this->laws, $this->kpis, $this->counts, $this->lawCategories);
    }

    public function openImage(int $id): void
    {
        $tool = $this->tool($id);
        $this->resetModal();
        $this->toolId = (int) $tool->getKey();
        $this->openModal('xmCccImage');
    }

    public function openCreate(): void
    {
        $this->authorizeManage();
        $this->createKind();
        $this->resetModal();
        $this->openModal('xmCccPaged');
    }

    public function openEdit(int $id): void
    {
        $tool = $this->paged($id);
        $this->resetModal();
        $this->toolId = (int) $tool->getKey();
        $this->title = $tool->title;
        $this->openModal('xmCccPaged');
    }

    public function openPages(int $id, ManageCccTools $manage): void
    {
        $tool = $this->paged($id);
        $this->resetModal();
        $this->toolId = (int) $tool->getKey();
        $this->pageEdits = array_map(
            static fn (array $page): array => ['caption' => $page['caption'], 'alt' => $page['alt']],
            $manage->pagesOf($tool),
        );
        $this->openModal('xmCccPages');
    }

    public function openDelete(int $id): void
    {
        $tool = $this->paged($id);
        $this->resetModal();
        $this->toolId = (int) $tool->getKey();
        $this->openModal('xmCccDelete');
    }

    public function resetModal(): void
    {
        $this->reset('lawId', 'lawTitle', 'lawSubtitle', 'lawCategory', 'lawSummary', 'lawSimple', 'lawUrl', 'lawPublished', 'toolId', 'removeIndex', 'image', 'title', 'published', 'cover', 'draftPages', 'newImage', 'newCaption', 'newAlt', 'pageEdits', 'announce');
        $this->resetErrorBag();
        $this->fileInputKey++;
        unset($this->openedTool, $this->openedPages, $this->openedLaw);
    }

    public function saveImage(ManageCccTools $manage): void
    {
        $this->authorizeManage();
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

    public function addDraftPage(): void
    {
        $this->authorizeManage();

        if (count($this->draftPages) < self::DRAFT_PAGES_MAX) {
            $this->draftPages[] = ['file' => null, 'caption' => '', 'alt' => ''];
        }
    }

    public function removeDraftPage(int $index): void
    {
        $this->authorizeManage();
        unset($this->draftPages[$index]);
        $this->draftPages = array_values($this->draftPages);
        $this->resetErrorBag();
    }

    /** Crée l'outil (`toolId` nul : BD ou boîte à images selon l'onglet) ou enregistre son titre. */
    public function saveTool(ManageCccTools $manage): void
    {
        $this->authorizeManage();
        $actor = $this->actor();
        $titleRule = ['required', 'string', 'max:'.ManageCccTools::TITLE_MAX];
        $titleLabel = ['title' => (string) __('admin_ccc.form.title_label')];

        if ($this->toolId !== null) {
            $tool = $this->openedPaged();
            $this->validate(['title' => $titleRule], [], $titleLabel);

            try {
                $manage->renameTool($tool, $this->title, $actor);
            } catch (ContentRefused $refused) {
                $this->addError('title', $refused->userMessage());

                return;
            }

            $this->closeModal('xmCccPaged');
            $this->toast((string) __('admin_ccc.toasts.renamed', ['title' => $tool->title]));
            $this->resetModal();
            unset($this->tools);

            return;
        }

        $kind = $this->createKind();
        $this->validate([
            'title' => $titleRule,
            'cover' => ['nullable', 'file', 'max:5120'],
            'published' => ['boolean'],
            'draftPages' => ['array', 'max:'.self::DRAFT_PAGES_MAX],
            'draftPages.*.file' => ['required', 'file', 'max:5120'],
            'draftPages.*.caption' => ['nullable', 'string', 'max:'.ManageCccTools::CAPTION_MAX],
            'draftPages.*.alt' => ['required', 'string', 'max:'.ManageCccTools::ALT_MAX],
        ], [], $titleLabel + [
            'cover' => (string) __('admin_ccc.form.cover_label'),
            'draftPages.*.file' => (string) __('admin_ccc.planches.image'),
            'draftPages.*.caption' => (string) __('admin_ccc.planches.caption'),
            'draftPages.*.alt' => (string) __('admin_ccc.planches.alt'),
        ]);

        $cover = $this->cover instanceof UploadedFile ? $this->cover : null;

        try {
            $tool = $manage->createTool($kind, $this->title, $cover, $this->draftEntries(), $this->published, $actor);
        } catch (ContentRefused $refused) {
            $this->addError($refused->errorCode() === 'paged_empty' ? 'published' : 'tool', $refused->userMessage());

            return;
        }

        $this->closeModal('xmCccPaged');
        $this->toast((string) __('admin_ccc.toasts.created', ['kind' => $tool->kind->label(), 'title' => $tool->title]));
        $this->resetModal();
        unset($this->tools, $this->kpis, $this->counts);
    }

    public function addPage(ManageCccTools $manage): void
    {
        $this->authorizeManage();
        $tool = $this->openedPaged();
        $this->validate([
            'newImage' => ['required', 'file', 'max:5120'],
            'newCaption' => ['nullable', 'string', 'max:'.ManageCccTools::CAPTION_MAX],
            'newAlt' => ['required', 'string', 'max:'.ManageCccTools::ALT_MAX],
        ], [], [
            'newImage' => (string) __('admin_ccc.planches.image'),
            'newCaption' => (string) __('admin_ccc.planches.caption'),
            'newAlt' => (string) __('admin_ccc.planches.alt'),
        ]);
        $file = $this->newImage;
        abort_unless($file instanceof UploadedFile, 422);

        try {
            $manage->addPage($tool, $file, $this->newCaption, $this->newAlt, $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('newImage', $refused->userMessage());

            return;
        }

        $this->pageEdits[] = ['caption' => trim($this->newCaption), 'alt' => trim($this->newAlt)];
        $this->reset('newImage', 'newCaption', 'newAlt');
        $this->fileInputKey++;
        $this->removeIndex = null;
        unset($this->openedTool, $this->openedPages, $this->tools);
        $this->toast((string) __('admin_ccc.toasts.page_added', ['title' => $tool->title]));
    }

    /** Légende et texte alternatif de toutes les planches. */
    public function savePages(ManageCccTools $manage): void
    {
        $this->authorizeManage();
        $tool = $this->openedPaged();
        $this->validate([
            'pageEdits.*.caption' => ['nullable', 'string', 'max:'.ManageCccTools::CAPTION_MAX],
            'pageEdits.*.alt' => ['required', 'string', 'max:'.ManageCccTools::ALT_MAX],
        ], [], [
            'pageEdits.*.caption' => (string) __('admin_ccc.planches.caption'),
            'pageEdits.*.alt' => (string) __('admin_ccc.planches.alt'),
        ]);

        $edits = [];

        foreach ($this->pageEdits as $row) {
            $edits[] = ['caption' => self::text($row, 'caption'), 'alt' => self::text($row, 'alt')];
        }

        try {
            $manage->savePages($tool, $edits, $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('pages', $refused->userMessage());

            return;
        }

        $this->toast((string) __('admin_ccc.toasts.pages_saved', ['title' => $tool->title]));
        unset($this->openedTool, $this->openedPages);
    }

    /** Monte (−1) ou descend (+1) une planche ; les textes saisis et pas encore enregistrés suivent leur planche. */
    public function movePage(int $index, int $direction, ManageCccTools $manage): void
    {
        $this->authorizeManage();
        $tool = $this->openedPaged();
        $direction = $direction < 0 ? -1 : 1;

        try {
            $target = $manage->movePage($tool, $index, $direction, $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('pages', $refused->userMessage());

            return;
        }

        if (isset($this->pageEdits[$index], $this->pageEdits[$target])) {
            [$this->pageEdits[$index], $this->pageEdits[$target]] = [$this->pageEdits[$target], $this->pageEdits[$index]];
        }

        $this->removeIndex = null;
        unset($this->openedTool, $this->openedPages);
        $total = count($this->openedPages());
        $this->announce = (string) __('admin_ccc.planches.moved', ['position' => $target + 1, 'total' => $total]);

        // Le focus suit la planche déplacée ; au bord de la liste, il passe sur l'autre bouton (le premier « Monter » est inactif).
        $button = $direction < 0 ? ($target === 0 ? 'Down' : 'Up') : ($target === $total - 1 ? 'Up' : 'Down');
        $this->dispatch('xm-ccc-focus', id: 'xmCccPage'.$button.$target);
    }

    public function askRemovePage(int $index): void
    {
        $this->authorizeManage();
        $this->openedPaged();

        if (isset($this->pageEdits[$index])) {
            $this->removeIndex = $index;
        }
    }

    public function cancelRemovePage(): void
    {
        $this->authorizeManage();
        $index = $this->removeIndex;
        $this->removeIndex = null;

        if ($index !== null) {
            $this->dispatch('xm-ccc-focus', id: 'xmCccPageRemove'.$index);
        }
    }

    public function removePage(ManageCccTools $manage): void
    {
        $this->authorizeManage();
        $tool = $this->openedPaged();
        $index = $this->removeIndex ?? abort(404);
        $this->removeIndex = null;

        try {
            $manage->removePage($tool, $index, $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('pages', $refused->userMessage());

            return;
        }

        array_splice($this->pageEdits, $index, 1);
        unset($this->openedTool, $this->openedPages, $this->tools);
        $this->toast((string) __('admin_ccc.toasts.page_removed', ['title' => $tool->title]));
        $this->dispatch('xm-ccc-focus', id: 'xmCccPagesList');
    }

    public function deleteTool(ManageCccTools $manage): void
    {
        $this->authorizeManage();
        $tool = $this->openedPaged();

        try {
            $manage->deleteTool($tool, $this->actor());
        } catch (ContentRefused $refused) {
            $this->addError('delete', $refused->userMessage());

            return;
        }

        $this->closeModal('xmCccDelete');
        $this->toast((string) __('admin_ccc.toasts.deleted', ['kind' => $tool->kind->label(), 'title' => $tool->title]));
        $this->resetModal();
        unset($this->tools, $this->kpis, $this->counts);
    }

    #[Computed]
    public function openedLaw(): ?Law
    {
        return $this->lawId === null ? null : Law::query()->find($this->lawId);
    }

    /**
     * Catégories déjà utilisées, proposées à la saisie (liste de suggestions du champ « Catégorie »).
     *
     * @return list<string>
     */
    #[Computed]
    public function lawCategories(): array
    {
        return Law::query()->orderBy('category')->distinct()->pluck('category')
            ->map(static fn (mixed $category): string => (string) $category)->values()->all();
    }

    #[Computed]
    public function openedTool(): ?CccTool
    {
        return $this->toolId === null ? null : CccTool::query()->find($this->toolId);
    }

    /**
     * Planches de l'outil ouvert, dans l'ordre de lecture.
     *
     * @return list<array{image: string, caption: string, alt: string}>
     */
    #[Computed]
    public function openedPages(): array
    {
        $tool = $this->openedTool();

        return $tool === null || ! ManageCccTools::isPaged($tool->kind) ? [] : app(ManageCccTools::class)->pagesOf($tool);
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
        $this->authorizeManage();

        return CccTool::query()->findOrFail($id);
    }

    /** BD ou boîte à images désignée par l'identifiant ; un outil d'une autre famille n'est jamais modifié par ces actions. */
    private function paged(int $id): CccTool
    {
        $this->authorizeManage();

        return CccTool::query()->whereIn('kind', [CccKind::Comic, CccKind::ImageBox])->findOrFail($id);
    }

    /** BD ou boîte à images dont une modale est ouverte (`toolId` verrouillé). */
    private function openedPaged(): CccTool
    {
        $this->authorizeManage();
        $tool = $this->openedTool();
        abort_unless($tool instanceof CccTool && ManageCccTools::isPaged($tool->kind), 404);

        return $tool;
    }

    /** Famille créée par l'onglet courant : BD SSR ou boîtes à images, jamais une autre. */
    private function createKind(): CccKind
    {
        $kind = CccKind::tryFrom($this->tab);
        abort_unless($kind !== null && ManageCccTools::isPaged($kind), 404);

        return $kind;
    }

    /** Famille de l'outil ouvert dans la modale, ou celle de l'onglet pour une création (textes de l'écran). */
    public function modalKind(): CccKind
    {
        $kind = $this->openedTool()?->kind ?? CccKind::tryFrom($this->tab);

        return $kind !== null && ManageCccTools::isPaged($kind) ? $kind : CccKind::Comic;
    }

    /** Texte de loi désigné par l'identifiant : `laws.manage` en plus de `ccc_tools.manage`, revérifiés à chaque action. */
    private function law(int $id): Law
    {
        $this->authorizeLaws();

        return Law::query()->findOrFail($id);
    }

    private function authorizeLaws(): void
    {
        $this->authorizeManage();
        abort_unless($this->actor()->can('laws.manage'), 403);
    }

    /** La permission est revérifiée à chaque action : un compte dont le rôle change garde l'écran ouvert, pas le droit d'écrire. */
    private function authorizeManage(): void
    {
        abort_unless($this->actor()->can('ccc_tools.manage'), 403);
    }

    /**
     * Planches initiales saisies dans le formulaire de création, relues ligne à ligne (la saisie vient du navigateur).
     *
     * @return list<array{file: UploadedFile, caption: string, alt: string}>
     */
    private function draftEntries(): array
    {
        $entries = [];

        foreach ($this->draftPages as $row) {
            $file = is_array($row) ? ($row['file'] ?? null) : null;

            if ($file instanceof UploadedFile) {
                $entries[] = ['file' => $file, 'caption' => self::text($row, 'caption'), 'alt' => self::text($row, 'alt')];
            }
        }

        return $entries;
    }

    private static function text(mixed $row, string $key): string
    {
        $value = is_array($row) ? ($row[$key] ?? null) : null;

        return is_string($value) ? $value : '';
    }

    private function actor(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
