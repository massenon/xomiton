{{--
    Onglet « Tags et centres d'intérêt » (composant enfant App\Livewire\Admin\Newsletter\Taxonomy) : une carte par famille
    (tags, centres d'intérêt) avec tableau (libellé, identifiant, usage, actions), modale création / renommage et modale
    de confirmation de suppression (refusée quand l'élément est utilisé).
--}}
<div class="xm-nl-taxonomy">
    <p class="text-secondary xm-prose mb-4">{{ __('admin_newsletter_taxonomy.intro') }}</p>

    @foreach ($groups as $kind => $items)
        <section class="card xm-admin-card mb-4" aria-labelledby="xmNlTax-{{ $kind }}-title">
            <div class="card-header d-flex flex-wrap align-items-center justify-content-between gap-2">
                <h2 class="h6 fw-bold mb-0" id="xmNlTax-{{ $kind }}-title">{{ __('admin_newsletter_taxonomy.kinds.'.$kind.'.title') }} <span class="badge bg-secondary-subtle text-secondary-emphasis ms-1">{{ $items->count() }}</span></h2>
                <button type="button" class="btn btn-primary btn-sm" wire:click="openCreate('{{ $kind }}')" wire:loading.attr="disabled" wire:target="openCreate('{{ $kind }}')">
                    <i class="bi bi-plus-lg me-1" aria-hidden="true"></i>{{ __('admin_newsletter_taxonomy.kinds.'.$kind.'.new') }}
                </button>
            </div>
            <x-admin.table :id="'xmNlTax-'.$kind" :caption="__('admin_newsletter_taxonomy.kinds.'.$kind.'.caption')" :empty="$items->isEmpty()" :columns="[
                ['key' => 'label', 'label' => __('admin_newsletter_taxonomy.columns.label')],
                ['key' => 'slug', 'label' => __('admin_newsletter_taxonomy.columns.slug')],
                ['key' => 'usage', 'label' => __('admin_newsletter_taxonomy.columns.usage')],
                ['key' => 'actions', 'label' => '', 'sr' => __('admin_newsletter_taxonomy.columns.actions'), 'class' => 'text-end'],
            ]">
                @foreach ($items as $item)
                    @php($use = $usage[$kind][$item->id])
                    <tr wire:key="tax-{{ $kind }}-{{ $item->id }}">
                        <td data-label="{{ __('admin_newsletter_taxonomy.columns.label') }}" class="fw-semibold text-break">{{ $item->label }}</td>
                        <td data-label="{{ __('admin_newsletter_taxonomy.columns.slug') }}"><code>{{ $item->slug }}</code></td>
                        <td data-label="{{ __('admin_newsletter_taxonomy.columns.usage') }}" class="small">
                            @if (array_sum($use) === 0)
                                <span class="text-secondary">{{ __('admin_newsletter_taxonomy.usage.none') }}</span>
                            @else
                                {{ __('admin_newsletter_taxonomy.usage.summary', ['subscribers' => $use['subscribers'], 'profiles' => $use['profiles'], 'campaigns' => $use['campaigns']]) }}
                            @endif
                        </td>
                        <td data-label="{{ __('admin_newsletter_taxonomy.columns.actions') }}" class="text-end xm-table__actions">
                            <div class="d-inline-flex flex-wrap justify-content-end gap-1">
                                <button type="button" class="btn btn-sm btn-light border" wire:click="openEdit('{{ $kind }}', {{ $item->id }})" wire:loading.attr="disabled" wire:target="openEdit('{{ $kind }}', {{ $item->id }})" title="{{ __('admin_newsletter_taxonomy.actions.rename') }}">
                                    <i class="bi bi-pencil" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_newsletter_taxonomy.actions.rename_of', ['label' => $item->label]) }}</span>
                                </button>
                                <button type="button" class="btn btn-sm btn-light border text-danger" wire:click="confirmDelete('{{ $kind }}', {{ $item->id }})" wire:loading.attr="disabled" wire:target="confirmDelete('{{ $kind }}', {{ $item->id }})" title="{{ __('admin_newsletter_taxonomy.actions.delete') }}">
                                    <i class="bi bi-trash" aria-hidden="true"></i><span class="visually-hidden">{{ __('admin_newsletter_taxonomy.actions.delete_of', ['label' => $item->label]) }}</span>
                                </button>
                            </div>
                        </td>
                    </tr>
                @endforeach
                <x-slot:emptyState>
                    <x-admin.empty icon="tags" :title="__('admin_newsletter_taxonomy.kinds.'.$kind.'.empty')">{{ __('admin_newsletter_taxonomy.empty_text') }}</x-admin.empty>
                </x-slot:emptyState>
            </x-admin.table>
        </section>
    @endforeach

    {{-- Création et renommage dans la même modale. --}}
    <x-admin.modal id="xmNlTaxonomyForm" size="md" type="form" heading="h2" :title="__('admin_newsletter_taxonomy.kinds.'.$kind.'.'.($itemId === 0 ? 'title_new' : 'title_edit'))" :dirty-guard="true">
        <form id="xmNlTaxonomyFormEl" wire:submit="save" novalidate>
            <label class="form-label" for="xmNlTaxLabel">{{ __('admin_newsletter_taxonomy.fields.label') }}</label>
            <input class="form-control @error('label') is-invalid @enderror" id="xmNlTaxLabel" wire:model="label" maxlength="60" required autocomplete="off" aria-describedby="xmNlTaxLabelHint @error('label') xmNlTaxLabelError @enderror" @error('label') aria-invalid="true" @enderror>
            <div class="form-text" id="xmNlTaxLabelHint">{{ __($itemId === 0 ? 'admin_newsletter_taxonomy.form.hint_new' : 'admin_newsletter_taxonomy.form.hint_edit') }}</div>
            @error('label')<div class="invalid-feedback d-block" id="xmNlTaxLabelError">{{ $message }}</div>@enderror
        </form>
        <x-slot:footer>
            <button type="submit" form="xmNlTaxonomyFormEl" class="btn btn-primary" wire:loading.attr="disabled" wire:target="save"><i class="bi bi-check2 me-1" aria-hidden="true"></i>{{ __('admin_newsletter_taxonomy.form.save') }}</button>
        </x-slot:footer>
    </x-admin.modal>

    {{-- Suppression : confirmation, ou explication du refus quand l'élément est utilisé. --}}
    <x-admin.modal id="xmNlTaxonomyDelete" type="delete" size="md" :title="__('admin_newsletter_taxonomy.delete.title')" static>
        @if ($target)
            @if ($targetUsage !== null && array_sum($targetUsage) > 0)
                <x-alert type="warning" icon="exclamation-triangle" role="status" class="mb-0">{{ __('admin_newsletter_taxonomy.delete.refused', ['label' => $target->label, 'usage' => __('admin_newsletter_taxonomy.usage.summary', ['subscribers' => $targetUsage['subscribers'], 'profiles' => $targetUsage['profiles'], 'campaigns' => $targetUsage['campaigns']])]) }}</x-alert>
            @else
                <p class="mb-0">{{ __('admin_newsletter_taxonomy.delete.text', ['label' => $target->label]) }}</p>
            @endif
        @endif
        <x-slot:footer>
            @if ($target && ($targetUsage === null || array_sum($targetUsage) === 0))
                <button type="button" class="btn btn-danger" wire:click="delete" wire:loading.attr="disabled" wire:target="delete"><i class="bi bi-trash me-1" aria-hidden="true"></i>{{ __('admin_newsletter_taxonomy.delete.confirm') }}</button>
            @endif
        </x-slot:footer>
    </x-admin.modal>
</div>
