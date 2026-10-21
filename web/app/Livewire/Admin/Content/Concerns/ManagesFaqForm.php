<?php

declare(strict_types=1);

namespace App\Livewire\Admin\Content\Concerns;

use App\Application\Admin\Content\ContentRules;
use App\Application\Admin\Content\ManageFaqs;
use App\Models\Faq;
use Livewire\Attributes\Locked;

/**
 * Onglet FAQ de /admin/contenu (P12-03) : modale, publier / masquer, ordre (monter / descendre), suppression.
 */
trait ManagesFaqForm
{
    #[Locked]
    public ?int $faqId = null;

    /** @var array<string, mixed> */
    public array $faqForm = [];

    /** @return array<string, mixed> */
    protected function blankFaq(): array
    {
        return ['question' => '', 'answer' => '', 'category_id' => '', 'is_published' => true, 'show_on_home' => false];
    }

    public function newFaq(): void
    {
        $this->authorize('create', Faq::class);
        $this->resetFaqForm();
        $this->openModal('xmContentFaq');
    }

    public function editFaq(int $id): void
    {
        $faq = Faq::query()->findOrFail($id);
        $this->authorize('update', $faq);
        $this->resetFaqForm();
        $this->faqId = (int) $faq->getKey();
        $this->faqForm = [
            'question' => (string) $faq->question, 'answer' => (string) $faq->answer, 'category_id' => (string) ($faq->category_id ?? ''),
            'is_published' => (bool) $faq->is_published, 'show_on_home' => (bool) $faq->show_on_home,
        ];
        $this->openModal('xmContentFaq');
    }

    public function resetFaqForm(): void
    {
        $this->reset('faqId');
        $this->faqForm = $this->blankFaq();
        $this->resetErrorBag();
    }

    public function saveFaq(ManageFaqs $manage): void
    {
        $faq = $this->faqId === null ? null : Faq::query()->findOrFail($this->faqId);
        $faq === null ? $this->authorize('create', Faq::class) : $this->authorize('update', $faq);

        $data = $this->validateForm(
            'faqForm',
            $this->blankToNull($this->faqForm, ['category_id']),
            ContentRules::faq(),
            (array) __('admin_content.faqs.fields'),
        );

        $saved = $manage->save($faq, $data, $this->actor());
        $this->closeModal('xmContentFaq');
        $this->resetFaqForm();
        unset($this->kpis, $this->faqHealth);
        $this->toast((string) __($saved->wasRecentlyCreated ? 'admin_content.faqs.toasts.created' : 'admin_content.faqs.toasts.saved'));
    }

    public function toggleFaq(int $id, ManageFaqs $manage): void
    {
        $faq = Faq::query()->findOrFail($id);
        $this->authorize('publish', $faq);
        $publish = ! $faq->is_published;
        $manage->setPublished($faq, $publish, $this->actor());
        unset($this->kpis, $this->faqHealth);
        $this->toast((string) __($publish ? 'admin_content.faqs.toasts.published' : 'admin_content.faqs.toasts.unpublished'));
    }

    public function moveFaq(int $id, string $direction, ManageFaqs $manage): void
    {
        $faq = Faq::query()->findOrFail($id);
        $this->authorize('update', $faq);
        $manage->move($faq, $direction === 'up' ? -1 : 1, $this->actor());
    }

    public function deleteFaq(int $id, ManageFaqs $manage): void
    {
        $faq = Faq::query()->findOrFail($id);
        $this->authorize('delete', $faq);
        $manage->delete($faq, $this->actor());
        unset($this->kpis, $this->faqHealth);
        $this->toast((string) __('admin_content.faqs.toasts.deleted'), 'warning');
    }
}
