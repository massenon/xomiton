{{-- Modale d'un référentiel : champs selon le genre de ligne ; le code (slug) est fixé à la création, le pays d'une zone
     seulement tant qu'elle n'est utilisée nulle part. --}}
@php
    $isNew = $itemId === null;
    $countryOptions = collect(\App\Application\Shared\Countries::all())->mapWithKeys(fn (array $c): array => [$c['value'] => $c['label']])->all();
    $slugHelp = $isNew ? __('admin_referentials.slug_help_new') : __('admin_referentials.slug_help_fixed');
    $modalTitle = __('admin_referentials.'.($kind === 'commune' ? 'communes' : $kind).($isNew ? '.new' : '.edit_title'));
@endphp
<x-admin.modal id="xmReferentialModal" size="md" heading="h2" dirty-guard :title="$modalTitle" x-data x-on:hidden-bs-modal.dot="$wire.resetForm()">
    <form id="xmReferentialForm" wire:submit="save" novalidate>
        <div class="row g-3">
            @if ($kind === 'zones')
                <div class="col-12">
                    <x-field name="form.country_code" type="select" :label="__('admin_referentials.fields.country_code')" wire:model="form.country_code" :options="$countryOptions" :disabled="$countryLocked"
                             :hint="$countryLocked ? __('admin_referentials.country_locked') : null" />
                </div>
            @endif
            <div class="col-12">
                <x-field name="form.label" :label="__('admin_referentials.fields.label')" wire:model.blur="form.label" maxlength="80" autocomplete="off" />
            </div>
            <div class="col-12">
                <x-field name="form.slug" :label="__('admin_referentials.fields.slug')" wire:model.blur="form.slug" maxlength="80" autocomplete="off" :hint="$slugHelp" :disabled="! $isNew" :optional="$isNew" />
            </div>
            @if ($kind === 'violence_types')
                <div class="col-12">
                    <x-field name="form.icon" :label="__('admin_referentials.fields.icon')" wire:model.blur="form.icon" maxlength="40" autocomplete="off" :hint="__('admin_referentials.icon_help')" />
                </div>
                <div class="col-12">
                    <x-field name="form.description" type="textarea" :label="__('admin_referentials.fields.description')" wire:model.blur="form.description" maxlength="255" rows="3" />
                </div>
            @elseif ($kind === 'needs')
                <div class="col-12">
                    <x-field name="form.routed_role" type="select" :label="__('admin_referentials.fields.routed_role')" wire:model="form.routed_role" :options="$roleOptions" :hint="__('admin_referentials.role_help')" />
                </div>
            @endif
            <div class="col-12">
                <x-field name="form.is_active" type="switch" :label="__('admin_referentials.fields.is_active')" wire:model="form.is_active" />
            </div>
        </div>
    </form>
    <x-slot:footer>
        <button type="submit" form="xmReferentialForm" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="bi bi-check-lg me-1" aria-hidden="true"></i>{{ __('admin_referentials.save') }}</button>
    </x-slot:footer>
</x-admin.modal>
