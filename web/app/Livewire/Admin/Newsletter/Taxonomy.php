<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Newsletter;

use App\Application\Admin\Newsletter\ManageNewsletterTaxonomy;
use App\Livewire\Concerns\InteractsWithUi;
use App\Models\NewsletterInterest;
use App\Models\NewsletterTag;
use App\Models\User;
use DomainException;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;

/**
 * Onglet « Tags et centres d'intérêt » de `/admin/newsletter` (composant enfant, P14-02) : liste, création, renommage et
 * suppression des tags de segmentation et des centres d'intérêt. Le slug est dérivé du libellé et ne change plus ;
 * la suppression est refusée tant que l'élément est utilisé (voir ManageNewsletterTaxonomy). Permission
 * `newsletter.manage_subscribers`, rejouée à chaque action (boot) puis par le cas d'usage.
 */
final class Taxonomy extends Component
{
    use InteractsWithUi;

    public const FORM_MODAL = 'xmNlTaxonomyForm';

    public const DELETE_MODAL = 'xmNlTaxonomyDelete';

    /** Famille de la modale ouverte : `tag` ou `interest` ; verrouillée. */
    #[Locked]
    public string $kind = ManageNewsletterTaxonomy::TAG;

    /** Élément modifié ou à supprimer (0 : création) ; verrouillé. */
    #[Locked]
    public int $itemId = 0;

    public string $label = '';

    public function boot(): void
    {
        $user = auth()->user();
        abort_unless($user instanceof User && $user->can('newsletter.manage_subscribers'), 403);
    }

    public function openCreate(string $kind): void
    {
        if (! in_array($kind, ManageNewsletterTaxonomy::KINDS, true)) {
            return;
        }

        $this->resetErrorBag();
        $this->kind = $kind;
        $this->itemId = 0;
        $this->label = '';
        $this->openModal(self::FORM_MODAL);
    }

    public function openEdit(string $kind, int $id): void
    {
        $item = ManageNewsletterTaxonomy::modelFor($kind, $id);

        if ($item === null) {
            $this->toast((string) __('admin_newsletter_taxonomy.errors.not_found'), 'error');

            return;
        }

        $this->resetErrorBag();
        $this->kind = $kind;
        $this->itemId = (int) $item->getKey();
        $this->label = (string) $item->label;
        $this->openModal(self::FORM_MODAL);
    }

    public function save(ManageNewsletterTaxonomy $manage): void
    {
        $this->resetErrorBag();
        $item = $this->itemId === 0 ? null : ManageNewsletterTaxonomy::modelFor($this->kind, $this->itemId);

        if ($this->itemId !== 0 && $item === null) {
            $this->toast((string) __('admin_newsletter_taxonomy.errors.not_found'), 'error');

            return;
        }

        try {
            $saved = $item === null
                ? $manage->create($this->user(), $this->kind, $this->label)
                : $manage->rename($this->user(), $this->kind, $item, $this->label);
        } catch (DomainException $e) {
            $this->addError('label', $e->getMessage());

            return;
        }

        $this->closeModal(self::FORM_MODAL);
        $this->toast((string) __($item === null ? 'admin_newsletter_taxonomy.toasts.created' : 'admin_newsletter_taxonomy.toasts.updated', ['label' => $saved->label]));
    }

    public function confirmDelete(string $kind, int $id): void
    {
        $item = ManageNewsletterTaxonomy::modelFor($kind, $id);

        if ($item === null) {
            $this->toast((string) __('admin_newsletter_taxonomy.errors.not_found'), 'error');

            return;
        }

        $this->kind = $kind;
        $this->itemId = (int) $item->getKey();
        $this->openModal(self::DELETE_MODAL);
    }

    public function delete(ManageNewsletterTaxonomy $manage): void
    {
        $item = ManageNewsletterTaxonomy::modelFor($this->kind, $this->itemId);
        $this->closeModal(self::DELETE_MODAL);

        if ($item === null) {
            $this->toast((string) __('admin_newsletter_taxonomy.errors.not_found'), 'error');

            return;
        }

        if (! $manage->delete($this->user(), $this->kind, $item)) {
            $this->toast((string) __('admin_newsletter_taxonomy.toasts.in_use', ['label' => $item->label]), 'error');

            return;
        }

        $this->itemId = 0;
        $this->toast((string) __('admin_newsletter_taxonomy.toasts.deleted', ['label' => $item->label]));
    }

    public function render(ManageNewsletterTaxonomy $manage): View
    {
        /** @var Collection<int, NewsletterTag> $tags */
        $tags = NewsletterTag::query()->ordered()->get();
        /** @var Collection<int, NewsletterInterest> $interests */
        $interests = NewsletterInterest::query()->ordered()->get();
        $target = $this->itemId === 0 ? null : ManageNewsletterTaxonomy::modelFor($this->kind, $this->itemId);

        $usage = [ManageNewsletterTaxonomy::TAG => [], ManageNewsletterTaxonomy::INTEREST => []];

        foreach ($tags as $tag) {
            $usage[ManageNewsletterTaxonomy::TAG][(int) $tag->getKey()] = $manage->usage(ManageNewsletterTaxonomy::TAG, $tag);
        }

        foreach ($interests as $interest) {
            $usage[ManageNewsletterTaxonomy::INTEREST][(int) $interest->getKey()] = $manage->usage(ManageNewsletterTaxonomy::INTEREST, $interest);
        }

        return view('livewire.admin.newsletter.taxonomy', [
            'groups' => [
                ManageNewsletterTaxonomy::TAG => $tags,
                ManageNewsletterTaxonomy::INTEREST => $interests,
            ],
            'usage' => $usage,
            'target' => $target,
            'targetUsage' => $target === null ? null : ($usage[$this->kind][(int) $target->getKey()] ?? null),
        ]);
    }

    private function user(): User
    {
        $user = auth()->user();
        abort_unless($user instanceof User, 403);

        return $user;
    }
}
