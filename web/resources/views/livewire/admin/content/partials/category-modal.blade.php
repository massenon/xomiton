{{-- Modale catégorie (P12-03) : type et slug fixes après la création. --}}
<x-admin.modal id="xmContentCategory" size="md" heading="h2" dirty-guard :title="$categoryId ? __('admin_content.categories.edit_title') : __('admin_content.categories.new')"
               x-data x-on:hidden-bs-modal.dot="$wire.resetCategoryForm()">
    <form id="xmContentCategoryForm" wire:submit="saveCategory" novalidate>
        <div class="row g-3">
            <div class="col-12">
                <x-field name="categoryForm.kind" type="select" :label="__('admin_content.categories.fields.kind')" wire:model="categoryForm.kind"
                         :options="collect(\App\Models\Category::KINDS)->mapWithKeys(fn ($k) => [$k => __('admin_content.categories.kinds.'.$k)])->all()" :disabled="(bool) $categoryId" />
            </div>
            <div class="col-12">
                <x-field name="categoryForm.label" :label="__('admin_content.categories.fields.label')" wire:model.blur="categoryForm.label" maxlength="80" autocomplete="off" />
            </div>
            <div class="col-12">
                <x-field name="categoryForm.slug" :label="__('admin_content.categories.fields.slug')" wire:model.blur="categoryForm.slug" maxlength="60" autocomplete="off" :hint="__('admin_content.categories.slug_help')" :disabled="(bool) $categoryId" />
            </div>
            <div class="col-md-6">
                <x-field name="categoryForm.icon" optional :label="__('admin_content.categories.fields.icon')" wire:model.blur="categoryForm.icon" maxlength="40" autocomplete="off" :hint="__('admin_content.categories.icon_help')" />
            </div>
            <div class="col-md-6">
                <x-field name="categoryForm.color" optional :label="__('admin_content.categories.fields.color')" wire:model.blur="categoryForm.color" maxlength="20" autocomplete="off" />
            </div>
            <div class="col-12">
                <x-field name="categoryForm.is_active" type="switch" :label="__('admin_content.categories.fields.is_active')" wire:model="categoryForm.is_active" />
            </div>
        </div>
    </form>
    <x-slot:footer>
        <button type="submit" form="xmContentCategoryForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="saveCategory"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_content.save') }}</button>
    </x-slot:footer>
</x-admin.modal>
