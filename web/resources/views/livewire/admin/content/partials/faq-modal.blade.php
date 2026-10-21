{{-- Modale question de la FAQ (P12-03) : réponse de 60 mots au plus. --}}
<x-admin.modal id="xmContentFaq" size="lg" heading="h2" dirty-guard :title="$faqId ? __('admin_content.faqs.edit_title') : __('admin_content.faqs.new')"
               x-data x-on:hidden-bs-modal.dot="$wire.resetFaqForm()">
    <form id="xmContentFaqForm" wire:submit="saveFaq" novalidate>
        <div class="row g-3">
            <div class="col-12">
                <x-field name="faqForm.question" :label="__('admin_content.faqs.fields.question')" wire:model.blur="faqForm.question" maxlength="255" autocomplete="off" />
            </div>
            <div class="col-12">
                <x-field name="faqForm.answer" type="textarea" :label="__('admin_content.faqs.fields.answer')" wire:model.live.debounce.400ms="faqForm.answer" rows="5" maxlength="2000"
                         :hint="__('admin_content.faqs.answer_help', ['count' => count(preg_split('/\s+/u', trim((string) ($faqForm['answer'] ?? '')), -1, PREG_SPLIT_NO_EMPTY) ?: []), 'max' => \App\Application\Admin\Content\ContentRules::FAQ_ANSWER_MAX_WORDS])" />
            </div>
            <div class="col-12">
                <x-field name="faqForm.category_id" type="select" optional :label="__('admin_content.faqs.fields.category_id')" wire:model.blur="faqForm.category_id"
                         :options="['' => __('admin_content.none')] + $this->categoryOptions->whereIn('kind', ['faq', 'thematique'])->pluck('label', 'id')->all()" :hint="__('admin_content.faqs.category_help')" />
            </div>
            <div class="col-md-6">
                <x-field name="faqForm.is_published" type="switch" :label="__('admin_content.faqs.fields.is_published')" wire:model="faqForm.is_published" />
            </div>
            <div class="col-md-6">
                <x-field name="faqForm.show_on_home" type="switch" :label="__('admin_content.faqs.fields.show_on_home')" wire:model="faqForm.show_on_home" :hint="__('admin_content.faqs.home_help')" />
            </div>
        </div>
    </form>
    <x-slot:footer>
        <button type="submit" form="xmContentFaqForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveFaq"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_content.save') }}</button>
    </x-slot:footer>
</x-admin.modal>
